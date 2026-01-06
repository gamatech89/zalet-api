<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Resources;

use App\Domains\Shared\Resources\BaseResource;
use App\Domains\Wallet\Models\LedgerEntry;
use Illuminate\Http\Request;

/**
 * Resource for a gift transaction (sent or received).
 *
 * @mixin LedgerEntry
 */
final class GiftTransactionResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $meta */
        $meta = $this->meta;

        $giftData = [
            'type' => $meta['gift_type'] ?? null,
            'name' => $meta['gift_name'] ?? null,
            'icon' => $meta['gift_icon'] ?? null,
            'animation' => $meta['gift_animation'] ?? null,
        ];

        return [
            'id' => $this->id,
            'transactionType' => $this->type,
            'credits' => $this->getAbsoluteAmount(),
            'gift' => $giftData,
            'referenceType' => $this->reference_type,
            'referenceId' => $this->reference_id,
            'liveSessionId' => $meta['live_session_id'] ?? null,
            'description' => $this->description,
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
