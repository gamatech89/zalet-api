<?php

declare(strict_types=1);

namespace App\Domains\Duel\Actions;

use App\Domains\Duel\Enums\DuelEventType;
use App\Domains\Duel\Enums\LiveSessionStatus;
use App\Domains\Duel\Enums\MessageType;
use App\Domains\Duel\Events\DuelGiftSent;
use App\Domains\Duel\Events\MessageSent;
use App\Domains\Duel\Models\DuelEvent;
use App\Domains\Duel\Models\LiveSession;
use App\Domains\Duel\Models\Message;
use App\Domains\Duel\Services\DuelScoreService;
use App\Domains\Identity\Models\User;
use App\Domains\Wallet\Actions\GetGiftCatalogAction;
use App\Domains\Wallet\Actions\SendGiftAction;
use Illuminate\Support\Str as LaravelStr;

/**
 * Action to send a gift during a live duel session.
 *
 * This action handles:
 * 1. Processing the gift payment via SendGiftAction
 * 2. Updating duel scores based on gift value
 * 3. Recording the duel event
 * 4. Creating a chat message
 * 5. Broadcasting real-time updates
 */
final readonly class SendDuelGiftAction
{
    public function __construct(
        private SendGiftAction $sendGiftAction,
        private DuelScoreService $scoreService,
        private GetGiftCatalogAction $giftCatalog
    ) {}

    /**
     * Send a gift to a participant in a live duel.
     *
     * @throws \InvalidArgumentException
     */
    public function execute(
        LiveSession $session,
        User $sender,
        User $recipient,
        string $giftSlug,
        int $quantity = 1
    ): void {
        // Validate session is active
        if ($session->status !== LiveSessionStatus::ACTIVE) {
            throw new \InvalidArgumentException('Can only send gifts during active duels.');
        }

        // Validate recipient is participant
        if (! in_array($recipient->id, [$session->host_id, $session->guest_id], true)) {
            throw new \InvalidArgumentException('Recipient must be a duel participant.');
        }

        // Validate sender is not a participant (viewers send gifts)
        // Actually, anyone can send gifts including participants
        // but typically viewers send gifts to influence the duel

        // Get gift data using gift catalog
        if (! $this->giftCatalog->isValidGiftType($giftSlug)) {
            throw new \InvalidArgumentException('Invalid gift slug.');
        }

        $gift = $this->giftCatalog->getGift($giftSlug);
        if ($gift === null) {
            throw new \InvalidArgumentException('Invalid gift slug.');
        }

        // Process the gift payment (debit sender, credit recipient)
        // Send each gift individually to properly track credits
        for ($i = 0; $i < $quantity; $i++) {
            $this->sendGiftAction->execute($sender, $recipient, $giftSlug, $session->id);
        }

        // Calculate points to add - gift credits * quantity
        $creditValue = $gift['credits'] * $quantity;

        // Update duel scores
        $party = $session->host_id === $recipient->id ? 'host' : 'guest';
        $this->scoreService->addPoints($session, $party, $creditValue);

        // Record duel event
        DuelEvent::create([
            'live_session_id' => $session->id,
            'actor_id' => $sender->id,
            'target_id' => $recipient->id,
            'event_type' => DuelEventType::GIFT_SENT,
            'payload' => [
                'gift_slug' => $giftSlug,
                'gift_name' => $gift['name'],
                'credit_value' => $creditValue,
                'quantity' => $quantity,
                'recipient_id' => $recipient->id,
                'party' => $party,
            ],
        ]);

        // Create chat message for the gift
        $recipientUsername = $recipient->profile->username ?? 'User';
        $message = Message::create([
            'uuid' => (string) LaravelStr::uuid(),
            'chat_room_id' => $session->chat_room_id,
            'user_id' => $sender->id,
            'type' => MessageType::GIFT,
            'content' => "sent {$quantity}x {$gift['icon']} {$gift['name']} to {$recipientUsername}!",
            'meta' => [
                'gift_slug' => $giftSlug,
                'gift_icon' => $gift['icon'],
                'credit_value' => $creditValue,
                'quantity' => $quantity,
                'recipient_id' => $recipient->id,
            ],
        ]);

        // Refresh session to get updated scores
        $session->refresh();

        // Broadcast events
        MessageSent::dispatch($message);
        DuelGiftSent::dispatch($session, $sender, $recipient, [
            'slug' => $giftSlug,
            'name' => $gift['name'],
            'icon' => $gift['icon'],
            'credit_value' => $creditValue,
        ]);
    }
}
