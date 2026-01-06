<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\PostType;
use App\Domains\Shared\Enums\VideoProvider;
use App\Domains\Streaming\Models\Post;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Post Model', function (): void {

    it('can create a post', function (): void {
        $post = Post::factory()->create([
            'title' => 'Test Video',
            'type' => PostType::Video,
        ]);

        expect($post->title)->toBe('Test Video')
            ->and($post->type)->toBe(PostType::Video)
            ->and($post->uuid)->not->toBeEmpty();
    });

    it('generates uuid on creation', function (): void {
        $post = Post::factory()->create();

        expect($post->uuid)
            ->not->toBeEmpty()
            ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
    });

    it('belongs to a user', function (): void {
        $user = User::factory()->create();
        $post = Post::factory()->forUser($user)->create();

        expect($post->user->id)->toBe($user->id);
    });

    it('scopes to published posts', function (): void {
        Post::factory()->create(['is_published' => true]);
        Post::factory()->create(['is_published' => false]);

        expect(Post::published()->count())->toBe(1);
    });

    it('scopes by post type', function (): void {
        Post::factory()->create(['type' => PostType::Video]);
        Post::factory()->create(['type' => PostType::ShortClip]);
        Post::factory()->create(['type' => PostType::Image]);

        expect(Post::ofType(PostType::Video)->count())->toBe(1)
            ->and(Post::ofType(PostType::ShortClip)->count())->toBe(1);
    });

    it('scopes by provider', function (): void {
        Post::factory()->youtube()->create();
        Post::factory()->vimeo()->create();

        expect(Post::fromProvider(VideoProvider::YouTube)->count())->toBe(1)
            ->and(Post::fromProvider(VideoProvider::Vimeo)->count())->toBe(1);
    });

    it('returns embed url for youtube', function (): void {
        $post = Post::factory()->youtube()->create([
            'provider_id' => 'dQw4w9WgXcQ',
        ]);

        expect($post->getEmbedUrl())->toBe('https://www.youtube.com/embed/dQw4w9WgXcQ');
    });

    it('returns embed url for vimeo', function (): void {
        $post = Post::factory()->vimeo()->create([
            'provider_id' => '123456789',
        ]);

        expect($post->getEmbedUrl())->toBe('https://player.vimeo.com/video/123456789');
    });

    it('identifies video posts', function (): void {
        $videoPost = Post::factory()->create(['type' => PostType::Video]);
        $imagePost = Post::factory()->image()->create();

        expect($videoPost->isVideo())->toBeTrue()
            ->and($imagePost->isVideo())->toBeFalse();
    });

    it('formats duration correctly', function (): void {
        $post1 = Post::factory()->create(['duration_seconds' => 65]);
        $post2 = Post::factory()->create(['duration_seconds' => 3661]);
        $post3 = Post::factory()->create(['duration_seconds' => null]);

        expect($post1->getFormattedDuration())->toBe('1:05')
            ->and($post2->getFormattedDuration())->toBe('1:01:01')
            ->and($post3->getFormattedDuration())->toBeNull();
    });

});
