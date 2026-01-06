<?php

declare(strict_types=1);

namespace App\Domains\Identity\Resources;

use App\Domains\Shared\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin \App\Domains\Identity\Models\Follow
 */
final class FollowResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'follower_id' => $this->follower->uuid,
            'following_id' => $this->following->uuid,
            'is_pending' => $this->accepted_at === null,
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
