<?php

declare(strict_types=1);

namespace App\Domains\Wallet\DTOs;

/**
 * Parsed webhook payload from payment provider.
 */
final readonly class WebhookPayload
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILURE = 'failure';
    public const STATUS_PENDING = 'pending';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @param string $orderIdentification Provider's order ID
     * @param string $transactionId Provider's transaction ID
     * @param string $status Payment status (success, failure, pending, cancelled)
     * @param string $responseCode Provider's response code
     * @param int $amountCents Transaction amount in cents
     * @param array<string, mixed> $meta Full webhook payload
     */
    public function __construct(
        public string $orderIdentification,
        public string $transactionId,
        public string $status,
        public string $responseCode,
        public int $amountCents,
        public array $meta = [],
    ) {}

    /**
     * Check if payment was successful.
     */
    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * Check if payment failed.
     */
    public function isFailure(): bool
    {
        return $this->status === self::STATUS_FAILURE;
    }

    /**
     * Check if payment was cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Create from array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            orderIdentification: $data['orderIdentification'],
            transactionId: $data['transactionId'],
            status: $data['status'],
            responseCode: $data['responseCode'] ?? '',
            amountCents: (int) ($data['amountCents'] ?? $data['amount'] ?? 0),
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
            'transactionId' => $this->transactionId,
            'status' => $this->status,
            'responseCode' => $this->responseCode,
            'amountCents' => $this->amountCents,
            'meta' => $this->meta,
        ];
    }
}
