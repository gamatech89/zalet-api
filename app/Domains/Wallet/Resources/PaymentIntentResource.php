<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Resources;

use App\Domains\Shared\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin \App\Domains\Wallet\Models\PaymentIntent
 */
final class PaymentIntentResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'provider' => $this->provider,
            'amountCents' => $this->amount_cents,
            'formattedAmount' => $this->getFormattedAmount(),
            'creditsAmount' => $this->credits_amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'isPending' => $this->isPending(),
            'isCompleted' => $this->isCompleted(),
            'isFailed' => $this->isFailed(),
            'packageId' => $this->meta['package_id'] ?? null,
            'packageName' => $this->meta['package_name'] ?? null,
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}
