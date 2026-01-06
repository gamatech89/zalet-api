<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Actions;

use App\Domains\Wallet\DTOs\WebhookPayload;
use App\Domains\Wallet\Models\PaymentIntent;
use Illuminate\Support\Facades\Log;

final class FailCreditPurchaseAction
{
    /**
     * Handle a failed credit purchase.
     *
     * @param PaymentIntent $intent The payment intent
     * @param WebhookPayload $payload The webhook payload
     */
    public function execute(PaymentIntent $intent, WebhookPayload $payload): void
    {
        // Verify intent is in correct state
        if ($intent->isFailed()) {
            Log::info('Payment intent already failed', [
                'intent_id' => $intent->id,
            ]);
            return;
        }

        // Update intent status
        $intent->update([
            'status' => PaymentIntent::STATUS_FAILED,
            'meta' => array_merge($intent->meta, [
                'failed_at' => now()->toIso8601String(),
                'failure_reason' => $payload->responseCode,
                'failure_status' => $payload->status,
            ]),
        ]);

        Log::warning('Credit purchase failed', [
            'intent_id' => $intent->id,
            'user_id' => $intent->user_id,
            'response_code' => $payload->responseCode,
            'status' => $payload->status,
        ]);

        // TODO: Dispatch event for notifications
        // event(new CreditPurchaseFailed($intent));
    }
}
