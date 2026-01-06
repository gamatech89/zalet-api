<?php

declare(strict_types=1);

namespace App\Domains\Streaming\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Streaming\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class GetUserPostsAction
{
    /**
     * Get posts for a specific user.
     *
     * @return LengthAwarePaginator<int, Post>
     */
    public function execute(
        User $user,
        bool $publishedOnly = true,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = Post::query()
            ->where('user_id', $user->id)
            ->with(['user.profile'])
            ->orderByDesc('created_at');

        if ($publishedOnly) {
            $query->published();
        }

        return $query->paginate($perPage);
    }
}
