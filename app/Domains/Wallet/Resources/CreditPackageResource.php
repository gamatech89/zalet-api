<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for credit packages.
 */
final class CreditPackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $package */
        $package = $this->resource;

        return [
            'id' => $package['id'],
            'name' => $package['name'] ?? ucfirst($package['id']),
            'credits' => $package['credits'],
            'priceCents' => $package['price_cents'],
            'formattedPrice' => number_format($package['price_cents'] / 100, 2) . ' ' . ($package['currency'] ?? 'EUR'),
            'currency' => $package['currency'] ?? 'EUR',
            'isPopular' => $package['popular'] ?? false,
        ];
    }
}
