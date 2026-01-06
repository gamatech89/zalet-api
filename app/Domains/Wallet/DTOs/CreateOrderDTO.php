<?php

declare(strict_types=1);

namespace App\Domains\Wallet\DTOs;

/**
 * Data transfer object for creating an order with the payment provider.
 */
final readonly class CreateOrderDTO
{
    /**
     * @param string $merchantOrderReference Unique order reference from our system
     * @param int $amountCents Amount in cents (e.g., 500 = €5.00)
     * @param string $currency Currency code (EUR, RSD, etc.)
     * @param string $customerEmail Customer's email address
     * @param string $customerReference Unique customer ID for one-click checkout
     * @param string $successUrl URL to redirect on successful payment
     * @param string $failureUrl URL to redirect on failed payment
     * @param string $cancelUrl URL to redirect on cancelled payment
     * @param string $notificationUrl Webhook URL for payment notifications
     * @param string|null $description Optional order description
     */
    public function __construct(
        public string $merchantOrderReference,
        public int $amountCents,
        public string $currency,
        public string $customerEmail,
        public string $customerReference,
        public string $successUrl,
        public string $failureUrl,
        public string $cancelUrl,
        public string $notificationUrl,
        public ?string $description = null,
    ) {}

    /**
     * Create from array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            merchantOrderReference: $data['merchantOrderReference'],
            amountCents: (int) $data['amountCents'],
            currency: $data['currency'] ?? 'EUR',
            customerEmail: $data['customerEmail'],
            customerReference: $data['customerReference'],
            successUrl: $data['successUrl'],
            failureUrl: $data['failureUrl'],
            cancelUrl: $data['cancelUrl'],
            notificationUrl: $data['notificationUrl'],
            description: $data['description'] ?? null,
        );
    }

    /**
     * Convert to array for API requests.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'merchantOrderReference' => $this->merchantOrderReference,
            'invoice' => [
                'amount' => $this->amountCents,
                'currency' => $this->currency,
            ],
            'customer' => [
                'email' => $this->customerEmail,
                'customerReference' => $this->customerReference,
            ],
            'urls' => [
                'successUrl' => $this->successUrl,
                'failureUrl' => $this->failureUrl,
                'cancelUrl' => $this->cancelUrl,
                'notificationUrl' => $this->notificationUrl,
            ],
            'description' => $this->description,
        ];
    }
}
