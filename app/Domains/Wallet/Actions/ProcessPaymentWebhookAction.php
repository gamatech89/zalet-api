<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Actions;

use App\Domains\Wallet\Contracts\PaymentProviderInterface;
use App\Domains\Wallet\DTOs\WebhookPayload;
use App\Domains\Wallet\Models\PaymentIntent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ProcessPaymentWebhookAction
{
    public function __construct(
        private readonly PaymentProviderInterface $paymentProvider,
        private readonly CompleteCreditPurchaseAction $completePurchase,
        private readonly FailCreditPurchaseAction $failPurchase,
    ) {}

    /**
     * Process incoming payment webhook.
     *
     * @param Request|array<string, mixed> $request The incoming webhook request or raw payload
     * @return bool Whether webhook was processed successfully
     * @throws \RuntimeException|\InvalidArgumentException If webhook cannot be processed
     */
    public function execute(Request|array $request): bool
    {
        // Parse webhook payload - accept either Request or raw array
        if ($request instanceof Request) {
            $payload = $this->paymentProvider->parseWebhook($request);
        } else {
            // Create payload directly from array (useful for testing)
            $payload = new WebhookPayload(
                orderIdentification: $request['orderIdentification'] ?? '',
                transactionId: $request['transactionId'] ?? '',
                status: $request['status'] ?? '',
                responseCode: $request['responseCode'] ?? null,
                amountCents: $request['amountCents'] ?? null,
            );
        }

        Log::info('Processing payment webhook', [
            'order_id' => $payload->orderIdentification,
            'status' => $payload->status,
            'transaction_id' => $payload->transactionId,
        ]);

        return DB::transaction(function () use ($payload): bool {
            // Find payment intent by provider order ID with lock
            $intent = PaymentIntent::where('provider_order_id', $payload->orderIdentification)
                ->lockForUpdate()
                ->first();

            if ($intent === null) {
                Log::warning('Payment intent not found for webhook', [
                    'order_id' => $payload->orderIdentification,
                ]);
                throw new \InvalidArgumentException("Payment intent not found: {$payload->orderIdentification}");
            }

            // Idempotency check - already processed?
            if ($intent->hasReceivedWebhook()) {
                Log::info('Duplicate webhook ignored', [
                    'intent_id' => $intent->id,
                    'order_id' => $payload->orderIdentification,
                ]);
                return true; // Return success to acknowledge webhook
            }

            // Mark webhook as received BEFORE processing
            $intent->markWebhookReceived();

            // Update meta with webhook data
            $intent->update([
                'meta' => array_merge($intent->meta, [
                    'webhook_payload' => $payload->toArray(),
                    'transaction_id' => $payload->transactionId,
                ]),
            ]);

            // Process based on status
            if ($payload->isSuccess()) {
                $this->completePurchase->execute($intent, $payload);
            } else {
                $this->failPurchase->execute($intent, $payload);
            }

            return true;
        });
    }
}
