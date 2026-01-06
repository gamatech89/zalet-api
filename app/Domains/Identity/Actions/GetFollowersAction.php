<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class GetFollowersAction
{
    /**
     * Get paginated list of followers for a user.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function execute(
        User $user,
        bool $acceptedOnly = true,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = User::query()
            ->select('users.*')
            ->join('follows', 'follows.follower_id', '=', 'users.id')
            ->where('follows.following_id', $user->id)
            ->with(['profile.originLocation', 'profile.currentLocation']);

        if ($acceptedOnly) {
            $query->whereNotNull('follows.accepted_at');
        }

        return $query->orderByDesc('follows.created_at')
            ->paginate($perPage);
    }
}
