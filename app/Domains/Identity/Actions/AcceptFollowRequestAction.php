<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\Follow;
use App\Domains\Identity\Models\User;

final class AcceptFollowRequestAction
{
    /**
     * Accept a pending follow request.
     */
    public function execute(User $user, User $follower): bool
    {
        $follow = Follow::where('follower_id', $follower->id)
            ->where('following_id', $user->id)
            ->whereNull('accepted_at')
            ->first();

        if (! $follow) {
            return false;
        }

        $follow->accepted_at = now();
        $follow->save();

        return true;
    }
}
