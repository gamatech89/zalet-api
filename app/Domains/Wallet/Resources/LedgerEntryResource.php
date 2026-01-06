<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Resources;

use App\Domains\Shared\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin \App\Domains\Wallet\Models\LedgerEntry
 */
final class LedgerEntryResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => $this->amount,
            'absoluteAmount' => $this->getAbsoluteAmount(),
            'balanceAfter' => $this->balance_after,
            'isCredit' => $this->isCredit(),
            'isDebit' => $this->isDebit(),
            'description' => $this->description,
            'referenceType' => $this->reference_type,
            'referenceId' => $this->reference_id,
            'meta' => $this->meta,
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
