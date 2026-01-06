<?php

declare(strict_types=1);

namespace App\Domains\Wallet\DTOs;

/**
 * Response from refund request.
 */
final readonly class RefundResponse
{
    /**
     * @param string $refundId Provider's refund ID
     * @param string $status Refund status
     * @param int $amountCents Refunded amount in cents
     * @param array<string, mixed> $meta Additional metadata
     */
    public function __construct(
        public string $refundId,
        public string $status,
        public int $amountCents,
        public array $meta = [],
    ) {}

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'refundId' => $this->refundId,
            'status' => $this->status,
            'amountCents' => $this->amountCents,
            'meta' => $this->meta,
        ];
    }
}
