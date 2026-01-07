<?php

declare(strict_types=1);

namespace App\Domains\Identity\Resources;

use App\Domains\Shared\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin \App\Domains\Identity\Models\User
 */
final class UserResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,  // numeric ID for relations
            'uuid' => $this->uuid,
            'email' => $this->email,
            'role' => $this->role->value,
            'emailVerifiedAt' => $this->email_verified_at?->toIso8601String(),
            'profile' => $this->whenLoaded('profile', fn () => new ProfileResource($this->profile)),
            'wallet' => $this->whenLoaded('wallet', function () {
                $wallet = $this->wallet;
                return $wallet ? [
                    'balance' => $wallet->balance,
                    'currency' => $wallet->currency,
                ] : null;
            }),
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
