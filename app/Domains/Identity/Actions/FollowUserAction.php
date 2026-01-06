<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\Follow;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Actions\Action;

final class FollowUserAction extends Action
{
    /**
     * Follow a user. If the target user has a private profile,
     * the follow will be pending until accepted.
     *
     * @throws \InvalidArgumentException
     */
    public function execute(User $follower, User $following): Follow
    {
        if ($follower->id === $following->id) {
            throw new \InvalidArgumentException('You cannot follow yourself.');
        }

        return $this->transaction(function () use ($follower, $following): Follow {
            // Check if already following
            $existingFollow = Follow::where('follower_id', $follower->id)
                ->where('following_id', $following->id)
                ->first();

            if ($existingFollow) {
                throw new \InvalidArgumentException('Already following this user.');
            }

            $isPrivate = $following->profile->is_private ?? false;

            return Follow::create([
                'follower_id' => $follower->id,
                'following_id' => $following->id,
                'accepted_at' => $isPrivate ? null : now(),
            ]);
        });
    }
}
