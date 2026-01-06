<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Wallet\Events\GiftSent;
use App\Domains\Wallet\Models\LedgerEntry;
use App\Domains\Wallet\Models\Wallet;
use Illuminate\Support\Facades\DB;

final class SendGiftAction
{
    public function __construct(
        private readonly GetGiftCatalogAction $giftCatalog,
    ) {}

    /**
     * Send a gift from one user to another.
     *
     * @param User $sender The user sending the gift
     * @param User $recipient The user receiving the gift
     * @param string $giftType The type of gift to send
     * @param int|null $liveSessionId Optional live session ID for duel context
     * @return array{sender_entry: LedgerEntry, recipient_entry: LedgerEntry, gift: array{name: string, credits: int, icon: string, animation: string}}
     * @throws \InvalidArgumentException If gift type is invalid
     * @throws \RuntimeException If sender has insufficient balance
     */
    public function execute(
        User $sender,
        User $recipient,
        string $giftType,
        ?int $liveSessionId = null,
    ): array {
        // Validate gift type
        if (!$this->giftCatalog->isValidGiftType($giftType)) {
            throw new \InvalidArgumentException("Invalid gift type: {$giftType}");
        }

        // Get gift details
        $gift = $this->giftCatalog->getGift($giftType);
        if ($gift === null) {
            throw new \InvalidArgumentException("Gift not found: {$giftType}");
        }

        $credits = $gift['credits'];

        // Cannot send gift to yourself
        if ($sender->id === $recipient->id) {
            throw new \InvalidArgumentException('Cannot send a gift to yourself');
        }

        return DB::transaction(function () use ($sender, $recipient, $giftType, $gift, $credits, $liveSessionId): array {
            // Get or create wallets
            $senderWallet = Wallet::firstOrCreate(
                ['user_id' => $sender->id],
                ['balance' => 0, 'currency' => 'CREDITS']
            );

            $recipientWallet = Wallet::firstOrCreate(
                ['user_id' => $recipient->id],
                ['balance' => 0, 'currency' => 'CREDITS']
            );

            // Build metadata
            $meta = [
                'gift_type' => $giftType,
                'gift_name' => $gift['name'],
                'gift_icon' => $gift['icon'],
                'gift_animation' => $gift['animation'],
            ];

            if ($liveSessionId !== null) {
                $meta['live_session_id'] = $liveSessionId;
            }

            // Debit sender wallet
            $senderEntry = $senderWallet->debit(
                amount: $credits,
                type: LedgerEntry::TYPE_GIFT_SENT,
                referenceType: User::class,
                referenceId: $recipient->id,
                description: "Sent {$gift['name']} gift to user #{$recipient->id}",
                meta: $meta,
            );

            // Credit recipient wallet
            $recipientEntry = $recipientWallet->credit(
                amount: $credits,
                type: LedgerEntry::TYPE_GIFT_RECEIVED,
                referenceType: User::class,
                referenceId: $sender->id,
                description: "Received {$gift['name']} gift from user #{$sender->id}",
                meta: $meta,
            );

            // Dispatch event for broadcasting (future: real-time updates)
            event(new GiftSent(
                senderId: $sender->id,
                recipientId: $recipient->id,
                giftType: $giftType,
                credits: $credits,
                liveSessionId: $liveSessionId,
            ));

            return [
                'sender_entry' => $senderEntry,
                'recipient_entry' => $recipientEntry,
                'gift' => $gift,
            ];
        });
    }
}
