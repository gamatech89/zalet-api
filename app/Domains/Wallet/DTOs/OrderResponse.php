<?php

declare(strict_types=1);

namespace App\Domains\Wallet\DTOs;

/**
 * Response from order creation.
 */
final readonly class OrderResponse
{
    /**
     * @param string $orderIdentification Provider's unique order ID
     * @param string $merchantOrderReference Our order reference
     * @param string $status Order status
     * @param array<string, mixed> $meta Additional metadata from provider
     */
    public function __construct(
        public string $orderIdentification,
        public string $merchantOrderReference,
        public string $status,
        public array $meta = [],
    ) {}

    /**
     * Create from provider response array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            orderIdentification: $data['orderIdentification'],
            merchantOrderReference: $data['merchantOrderReference'],
            status: $data['status'] ?? 'created',
            meta: $data,
        );
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'orderIdentification' => $this->orderIdentification,
            'merchantOrderReference' => $this->merchantOrderReference,
            'status' => $this->status,
            'meta' => $this->meta,
        ];
    }
}
