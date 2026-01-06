<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\Follow;
use App\Domains\Identity\Models\User;

final class RejectFollowRequestAction
{
    /**
     * Reject (delete) a pending follow request.
     */
    public function execute(User $user, User $follower): bool
    {
        return Follow::where('follower_id', $follower->id)
            ->where('following_id', $user->id)
            ->whereNull('accepted_at')
            ->delete() > 0;
    }
}
