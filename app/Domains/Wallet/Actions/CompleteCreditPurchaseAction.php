<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Actions;

use App\Domains\Wallet\DTOs\WebhookPayload;
use App\Domains\Wallet\Models\LedgerEntry;
use App\Domains\Wallet\Models\PaymentIntent;
use App\Domains\Wallet\Models\Wallet;
use Illuminate\Support\Facades\Log;

final class CompleteCreditPurchaseAction
{
    /**
     * Complete a successful credit purchase.
     *
     * @param PaymentIntent $intent The payment intent
     * @param WebhookPayload $payload The webhook payload
     */
    public function execute(PaymentIntent $intent, WebhookPayload $payload): void
    {
        // Verify intent is in correct state
        if ($intent->isCompleted()) {
            Log::info('Payment intent already completed', [
                'intent_id' => $intent->id,
            ]);
            return;
        }

        // Get or create user's wallet
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $intent->user_id],
            ['balance' => 0, 'currency' => 'CREDITS']
        );

        // Credit the wallet
        $wallet->credit(
            amount: $intent->credits_amount,
            type: LedgerEntry::TYPE_DEPOSIT,
            referenceType: PaymentIntent::class,
            referenceId: $intent->id,
            description: "Credit purchase: {$intent->credits_amount} credits",
            meta: [
                'payment_intent_uuid' => $intent->uuid,
                'amount_cents' => $intent->amount_cents,
                'currency' => $intent->currency,
                'transaction_id' => $payload->transactionId,
            ],
        );

        // Update intent status
        $intent->update([
            'status' => PaymentIntent::STATUS_COMPLETED,
            'meta' => array_merge($intent->meta, [
                'completed_at' => now()->toIso8601String(),
                'credited_amount' => $intent->credits_amount,
            ]),
        ]);

        Log::info('Credit purchase completed', [
            'intent_id' => $intent->id,
            'user_id' => $intent->user_id,
            'credits' => $intent->credits_amount,
            'new_balance' => $wallet->balance,
        ]);

        // TODO: Dispatch event for notifications
        // event(new CreditPurchaseCompleted($intent));
    }
}
