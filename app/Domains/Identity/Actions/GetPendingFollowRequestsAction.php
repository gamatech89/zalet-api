<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\Follow;
use App\Domains\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class GetPendingFollowRequestsAction
{
    /**
     * Get pending follow requests for a user (people wanting to follow them).
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function execute(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return User::query()
            ->select('users.*')
            ->join('follows', 'follows.follower_id', '=', 'users.id')
            ->where('follows.following_id', $user->id)
            ->whereNull('follows.accepted_at')
            ->with(['profile.originLocation', 'profile.currentLocation'])
            ->orderByDesc('follows.created_at')
            ->paginate($perPage);
    }
}
