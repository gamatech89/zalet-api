<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Wallet\Models\LedgerEntry;
use App\Domains\Wallet\Models\Wallet;
use Illuminate\Support\Facades\DB;

final class TransferCreditsAction
{
    /**
     * Transfer credits from one user to another.
     *
     * @param int $amount Amount to transfer (positive integer)
     * @param string|null $description Optional description
     * @return array{sender_entry: LedgerEntry, recipient_entry: LedgerEntry}
     * @throws \InvalidArgumentException If amount is invalid
     * @throws \RuntimeException If sender has insufficient balance
     */
    public function execute(
        User $sender,
        User $recipient,
        int $amount,
        ?string $description = null,
    ): array {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Transfer amount must be positive');
        }

        if ($sender->id === $recipient->id) {
            throw new \InvalidArgumentException('Cannot transfer to yourself');
        }

        return DB::transaction(function () use ($sender, $recipient, $amount, $description): array {
            // Get or create wallets
            $senderWallet = Wallet::firstOrCreate(
                ['user_id' => $sender->id],
                ['balance' => 0, 'currency' => 'CREDITS']
            );

            $recipientWallet = Wallet::firstOrCreate(
                ['user_id' => $recipient->id],
                ['balance' => 0, 'currency' => 'CREDITS']
            );

            // Debit sender
            $senderEntry = $senderWallet->debit(
                amount: $amount,
                type: LedgerEntry::TYPE_GIFT_SENT,
                referenceType: User::class,
                referenceId: $recipient->id,
                description: $description ?? "Transfer to user #{$recipient->id}",
            );

            // Credit recipient
            $recipientEntry = $recipientWallet->credit(
                amount: $amount,
                type: LedgerEntry::TYPE_GIFT_RECEIVED,
                referenceType: User::class,
                referenceId: $sender->id,
                description: $description ?? "Transfer from user #{$sender->id}",
            );

            return [
                'sender_entry' => $senderEntry,
                'recipient_entry' => $recipientEntry,
            ];
        });
    }
}
