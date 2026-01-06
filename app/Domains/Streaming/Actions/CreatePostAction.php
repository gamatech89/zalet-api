<?php

declare(strict_types=1);

namespace App\Domains\Streaming\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Actions\Action;
use App\Domains\Shared\Enums\PostType;
use App\Domains\Streaming\Models\Post;
use App\Domains\Streaming\Services\VideoParserService;

final class CreatePostAction extends Action
{
    public function __construct(
        private readonly VideoParserService $videoParser,
    ) {}

    /**
     * Create a new post.
     *
     * @param array{
     *     type: PostType,
     *     source_url: string,
     *     title?: string|null,
     *     description?: string|null,
     *     is_premium?: bool,
     *     is_published?: bool,
     * } $data
     */
    public function execute(User $user, array $data): Post
    {
        return $this->transaction(function () use ($user, $data): Post {
            $postData = [
                'user_id' => $user->id,
                'type' => $data['type'],
                'source_url' => $data['source_url'],
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'is_premium' => $data['is_premium'] ?? false,
                'is_published' => $data['is_published'] ?? true,
                'published_at' => ($data['is_published'] ?? true) ? now() : null,
            ];

            // Parse video URL for supported providers
            if ($data['type']->supportsProvider()) {
                $videoData = $this->videoParser->parse($data['source_url']);

                if ($videoData !== null) {
                    $postData['provider'] = $videoData['provider'];
                    $postData['provider_id'] = $videoData['id'];
                    $postData['thumbnail_url'] = $videoData['provider']->getThumbnailUrl($videoData['id']);
                }
            }

            return Post::create($postData);
        });
    }
}
