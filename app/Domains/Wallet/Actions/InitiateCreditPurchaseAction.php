<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Wallet\Contracts\PaymentProviderInterface;
use App\Domains\Wallet\DTOs\CreateOrderDTO;
use App\Domains\Wallet\DTOs\CreateSessionDTO;
use App\Domains\Wallet\Models\PaymentIntent;

final class InitiateCreditPurchaseAction
{
    public function __construct(
        private readonly PaymentProviderInterface $paymentProvider,
    ) {}

    /**
     * Initiate a credit purchase.
     *
     * @param User $user The user making the purchase
     * @param string $packageId The credit package ID
     * @param string $language Preferred language for payment form
     * @return array{intent: PaymentIntent, paymentUrl: string}
     * @throws \InvalidArgumentException If package not found
     */
    public function execute(
        User $user,
        string $packageId,
        string $language = 'en',
    ): array {
        // Get package details
        $package = $this->getPackage($packageId);

        if ($package === null) {
            throw new \InvalidArgumentException("Invalid package ID: {$packageId}");
        }

        // Generate unique idempotency key
        $idempotencyKey = $this->generateIdempotencyKey($user->id, $packageId);

        // Check for existing intent with same idempotency key (pending or processing)
        $existingIntent = PaymentIntent::where('idempotency_key', $idempotencyKey)
            ->whereIn('status', [PaymentIntent::STATUS_PENDING, PaymentIntent::STATUS_PROCESSING])
            ->first();

        // Return existing intent if it has a payment URL
        if ($existingIntent !== null && $existingIntent->provider_session_url !== null) {
            return [
                'intent' => $existingIntent,
                'paymentUrl' => $existingIntent->provider_session_url,
            ];
        }

        // Use existing intent or create a new one
        if ($existingIntent !== null) {
            $intent = $existingIntent;
        } else {
            // Create payment intent
            $intent = PaymentIntent::create([
                'user_id' => $user->id,
                'provider' => $this->paymentProvider->getProviderName(),
                'amount_cents' => $package['price_cents'],
                'credits_amount' => $package['credits'],
                'currency' => $package['currency'],
                'status' => PaymentIntent::STATUS_PENDING,
                'idempotency_key' => $idempotencyKey,
                'meta' => [
                    'package_id' => $packageId,
                    'package_name' => $package['name'] ?? $packageId,
                ],
            ]);
        }

        // Create order with payment provider
        $orderDto = new CreateOrderDTO(
            merchantOrderReference: $intent->uuid,
            amountCents: $package['price_cents'],
            currency: $package['currency'],
            customerEmail: $user->email,
            customerReference: $user->uuid,
            successUrl: $this->buildCallbackUrl('success', $intent->uuid),
            failureUrl: $this->buildCallbackUrl('failure', $intent->uuid),
            cancelUrl: $this->buildCallbackUrl('cancel', $intent->uuid),
            notificationUrl: route('webhooks.raiaccept'),
            description: "Credit purchase: {$package['credits']} credits",
        );

        $orderResponse = $this->paymentProvider->createOrder($orderDto);

        // Create payment session
        $sessionDto = new CreateSessionDTO(
            orderIdentification: $orderResponse->orderIdentification,
            language: $language,
        );

        $sessionResponse = $this->paymentProvider->createPaymentSession($sessionDto);

        // Update intent with provider details
        $intent->update([
            'provider_order_id' => $orderResponse->orderIdentification,
            'provider_session_url' => $sessionResponse->sessionUrl,
            'status' => PaymentIntent::STATUS_PROCESSING,
            'meta' => array_merge($intent->meta, [
                'provider_order_response' => $orderResponse->toArray(),
            ]),
        ]);

        /** @var PaymentIntent $freshIntent */
        $freshIntent = $intent->fresh();

        return [
            'intent' => $freshIntent,
            'paymentUrl' => $sessionResponse->sessionUrl,
        ];
    }

    /**
     * Get package details by ID.
     *
     * @return array<string, mixed>|null
     */
    private function getPackage(string $packageId): ?array
    {
        $packages = config('services.credit_packages', []);

        foreach ($packages as $package) {
            if ($package['id'] === $packageId) {
                return $package;
            }
        }

        return null;
    }

    /**
     * Generate idempotency key for this purchase.
     */
    private function generateIdempotencyKey(int $userId, string $packageId): string
    {
        // Include timestamp rounded to 5-minute window to allow retry
        $timeWindow = floor(time() / 300);

        return hash('sha256', "{$userId}:{$packageId}:{$timeWindow}");
    }

    /**
     * Build callback URL for payment result.
     */
    private function buildCallbackUrl(string $result, string $intentUuid): string
    {
        $baseUrl = config('app.frontend_url', config('app.url'));

        return "{$baseUrl}/wallet/purchase/{$intentUuid}/{$result}";
    }
}
