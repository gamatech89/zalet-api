<?php

declare(strict_types=1);

namespace App\Domains\Streaming\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Actions\Action;
use App\Domains\Streaming\Models\Post;

final class DeletePostAction extends Action
{
    /**
     * Delete a post.
     *
     * @throws \InvalidArgumentException
     */
    public function execute(User $user, Post $post): bool
    {
        if ($post->user_id !== $user->id) {
            throw new \InvalidArgumentException('You can only delete your own posts.');
        }

        return $this->transaction(function () use ($post): bool {
            return (bool) $post->delete();
        });
    }
}
