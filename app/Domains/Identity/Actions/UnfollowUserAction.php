<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\Follow;
use App\Domains\Identity\Models\User;

final class UnfollowUserAction
{
    /**
     * Unfollow a user (removes follow relationship).
     */
    public function execute(User $follower, User $following): bool
    {
        return Follow::where('follower_id', $follower->id)
            ->where('following_id', $following->id)
            ->delete() > 0;
    }
}
