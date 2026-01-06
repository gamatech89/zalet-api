<?php

declare(strict_types=1);

namespace App\Domains\Wallet\DTOs;

/**
 * Data transfer object for creating a payment session.
 */
final readonly class CreateSessionDTO
{
    /**
     * @param string $orderIdentification Provider's order ID
     * @param string $language Display language for payment form (sr, en, de)
     */
    public function __construct(
        public string $orderIdentification,
        public string $language = 'en',
    ) {}
}
