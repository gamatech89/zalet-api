<?php

declare(strict_types=1);

namespace App\Domains\Wallet\DTOs;

use Carbon\Carbon;

/**
 * Response from payment session creation.
 */
final readonly class SessionResponse
{
    /**
     * @param string $sessionUrl URL to the payment form
     * @param Carbon $expiresAt When the session expires
     */
    public function __construct(
        public string $sessionUrl,
        public Carbon $expiresAt,
    ) {}
}
