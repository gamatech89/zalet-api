<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for creator earnings summary.
 */
final class CreatorEarningsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        return [
            'totalCredits' => $data['total_credits'] ?? 0,
            'totalGiftsReceived' => $data['total_gifts_received'] ?? 0,
            'periodStart' => $data['period_start'] ?? null,
            'periodEnd' => $data['period_end'] ?? null,
        ];
    }
}
