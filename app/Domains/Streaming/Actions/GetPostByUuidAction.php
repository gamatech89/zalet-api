<?php

declare(strict_types=1);

namespace App\Domains\Streaming\Actions;

use App\Domains\Streaming\Models\Post;

final class GetPostByUuidAction
{
    /**
     * Get a post by UUID.
     */
    public function execute(string $uuid, bool $withRelations = true): ?Post
    {
        $query = Post::where('uuid', $uuid);

        if ($withRelations) {
            $query->with(['user.profile']);
        }

        return $query->first();
    }
}
