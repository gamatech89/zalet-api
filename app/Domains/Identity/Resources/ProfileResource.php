<?php

declare(strict_types=1);

namespace App\Domains\Identity\Resources;

use App\Domains\Shared\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin \App\Domains\Identity\Models\Profile
 */
final class ProfileResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'username' => $this->username,
            'displayName' => $this->display_name,
            'bio' => $this->bio,
            'avatarUrl' => $this->avatar_url,
            'isPrivate' => $this->is_private,
            'originLocation' => $this->whenLoaded(
                'originLocation',
                fn () => new LocationResource($this->originLocation)
            ),
            'currentLocation' => $this->whenLoaded(
                'currentLocation',
                fn () => new LocationResource($this->currentLocation)
            ),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}
