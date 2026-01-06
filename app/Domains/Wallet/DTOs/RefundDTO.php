<?php

declare(strict_types=1);

namespace App\Domains\Wallet\DTOs;

/**
 * Data transfer object for refund requests.
 */
final readonly class RefundDTO
{
    /**
     * @param string $orderIdentification Provider's order ID
     * @param string $transactionId Transaction ID to refund
     * @param int|null $amountCents Amount to refund in cents (null = full refund)
     */
    public function __construct(
        public string $orderIdentification,
        public string $transactionId,
        public ?int $amountCents = null,
    ) {}

    /**
     * Check if this is a partial refund.
     */
    public function isPartialRefund(): bool
    {
        return $this->amountCents !== null;
    }
}
