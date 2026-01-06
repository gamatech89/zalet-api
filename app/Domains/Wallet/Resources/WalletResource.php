<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Resources;

use App\Domains\Shared\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin \App\Domains\Wallet\Models\Wallet
 */
final class WalletResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'balance' => $this->balance,
            'formattedBalance' => $this->getFormattedBalance(),
            'currency' => $this->currency,
            'recentTransactions' => $this->whenLoaded(
                'ledgerEntries',
                fn () => LedgerEntryResource::collection($this->ledgerEntries)
            ),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}
