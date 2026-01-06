<?php

declare(strict_types=1);

namespace App\Domains\Streaming\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Actions\Action;
use App\Domains\Streaming\Models\Post;
use App\Domains\Streaming\Services\VideoParserService;

final class UpdatePostAction extends Action
{
    public function __construct(
        private readonly VideoParserService $videoParser,
    ) {}

    /**
     * Update an existing post.
     *
     * @param array{
     *     title?: string|null,
     *     description?: string|null,
     *     source_url?: string,
     *     is_premium?: bool,
     *     is_published?: bool,
     * } $data
     * @throws \InvalidArgumentException
     */
    public function execute(User $user, Post $post, array $data): Post
    {
        if ($post->user_id !== $user->id) {
            throw new \InvalidArgumentException('You can only update your own posts.');
        }

        return $this->transaction(function () use ($post, $data): Post {
            $updateData = [];

            if (array_key_exists('title', $data)) {
                $updateData['title'] = $data['title'];
            }

            if (array_key_exists('description', $data)) {
                $updateData['description'] = $data['description'];
            }

            if (array_key_exists('is_premium', $data)) {
                $updateData['is_premium'] = $data['is_premium'];
            }

            if (array_key_exists('is_published', $data)) {
                $updateData['is_published'] = $data['is_published'];

                // Set published_at if publishing for first time
                if ($data['is_published'] && $post->published_at === null) {
                    $updateData['published_at'] = now();
                }
            }

            // Handle source URL update (re-parse video)
            if (isset($data['source_url']) && $data['source_url'] !== $post->source_url) {
                $updateData['source_url'] = $data['source_url'];

                if ($post->type->supportsProvider()) {
                    $videoData = $this->videoParser->parse($data['source_url']);

                    if ($videoData !== null) {
                        $updateData['provider'] = $videoData['provider'];
                        $updateData['provider_id'] = $videoData['id'];
                        $updateData['thumbnail_url'] = $videoData['provider']->getThumbnailUrl($videoData['id']);
                    } else {
                        $updateData['provider'] = null;
                        $updateData['provider_id'] = null;
                        $updateData['thumbnail_url'] = null;
                    }
                }
            }

            $post->update($updateData);

            return $post->fresh() ?? $post;
        });
    }
}
