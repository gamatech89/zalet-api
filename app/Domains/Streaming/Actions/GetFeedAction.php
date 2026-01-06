<?php

declare(strict_types=1);

namespace App\Domains\Streaming\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Enums\PostType;
use App\Domains\Streaming\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class GetFeedAction
{
    /**
     * Get the main content feed.
     *
     * Feed algorithm:
     * 1. Posts from users the current user follows
     * 2. Posts from users in same location (origin or current)
     * 3. Popular recent posts
     *
     * @return LengthAwarePaginator<int, Post>
     */
    public function execute(
        ?User $user = null,
        ?PostType $type = null,
        ?int $locationId = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = Post::query()
            ->with(['user.profile.originLocation', 'user.profile.currentLocation'])
            ->published()
            ->orderByDesc('published_at');

        // Filter by type if specified
        if ($type !== null) {
            $query->ofType($type);
        }

        // For authenticated users, prioritize followed users' content
        if ($user !== null) {
            // Get IDs of users being followed
            $followingIds = $user->following()
                ->whereNotNull('accepted_at')
                ->pluck('following_id')
                ->toArray();

            if (! empty($followingIds)) {
                // Order by followed users first, then by date
                $query->orderByRaw(
                    'CASE WHEN user_id IN (' . implode(',', $followingIds) . ') THEN 0 ELSE 1 END'
                );
            }
        }

        // Filter by location if specified
        if ($locationId !== null) {
            $query->whereHas('user.profile', function ($q) use ($locationId): void {
                $q->where('origin_location_id', $locationId)
                    ->orWhere('current_location_id', $locationId);
            });
        }

        return $query->paginate($perPage);
    }
}
