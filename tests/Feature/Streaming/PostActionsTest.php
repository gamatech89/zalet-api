<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Enums\PostType;
use App\Domains\Streaming\Actions\CreatePostAction;
use App\Domains\Streaming\Actions\DeletePostAction;
use App\Domains\Streaming\Actions\GetFeedAction;
use App\Domains\Streaming\Actions\GetPostByUuidAction;
use App\Domains\Streaming\Actions\GetUserPostsAction;
use App\Domains\Streaming\Actions\UpdatePostAction;
use App\Domains\Streaming\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('CreatePostAction', function (): void {

    it('creates a post with youtube url', function (): void {
        $user = User::factory()->create();
        $action = app(CreatePostAction::class);

        $post = $action->execute($user, [
            'title' => 'My YouTube Video',
            'description' => 'A great video',
            'type' => PostType::Video,
            'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        expect($post->title)->toBe('My YouTube Video')
            ->and($post->provider->value)->toBe('youtube')
            ->and($post->provider_id)->toBe('dQw4w9WgXcQ')
            ->and($post->thumbnail_url)->toContain('dQw4w9WgXcQ')
            ->and($post->is_published)->toBeTrue();
    });

    it('creates a post with vimeo url', function (): void {
        $user = User::factory()->create();
        $action = app(CreatePostAction::class);

        $post = $action->execute($user, [
            'title' => 'My Vimeo Video',
            'type' => PostType::Video,
            'source_url' => 'https://vimeo.com/123456789',
        ]);

        expect($post->provider->value)->toBe('vimeo')
            ->and($post->provider_id)->toBe('123456789');
    });

    it('creates draft post when is_published is false', function (): void {
        $user = User::factory()->create();
        $action = app(CreatePostAction::class);

        $post = $action->execute($user, [
            'title' => 'Draft Post',
            'type' => PostType::Video,
            'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'is_published' => false,
        ]);

        expect($post->is_published)->toBeFalse()
            ->and($post->published_at)->toBeNull();
    });

});

describe('UpdatePostAction', function (): void {

    it('updates post title', function (): void {
        $user = User::factory()->create();
        $post = Post::factory()->forUser($user)->create(['title' => 'Old Title']);
        $action = app(UpdatePostAction::class);

        $updated = $action->execute($user, $post, ['title' => 'New Title']);

        expect($updated->title)->toBe('New Title');
    });

    it('throws exception when user does not own post', function (): void {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $post = Post::factory()->forUser($owner)->create();
        $action = app(UpdatePostAction::class);

        expect(fn () => $action->execute($otherUser, $post, ['title' => 'Hacked']))
            ->toThrow(InvalidArgumentException::class);
    });

    it('re-parses video url when source_url changes', function (): void {
        $user = User::factory()->create();
        $post = Post::factory()->forUser($user)->youtube()->create([
            'provider_id' => 'oldVideoId',
        ]);
        $action = app(UpdatePostAction::class);

        $updated = $action->execute($user, $post, [
            'source_url' => 'https://vimeo.com/987654321',
        ]);

        expect($updated->provider->value)->toBe('vimeo')
            ->and($updated->provider_id)->toBe('987654321');
    });

});

describe('DeletePostAction', function (): void {

    it('deletes post owned by user', function (): void {
        $user = User::factory()->create();
        $post = Post::factory()->forUser($user)->create();
        $action = app(DeletePostAction::class);

        $action->execute($user, $post);

        expect(Post::find($post->id))->toBeNull();
    });

    it('throws exception when user does not own post', function (): void {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $post = Post::factory()->forUser($owner)->create();
        $action = app(DeletePostAction::class);

        expect(fn () => $action->execute($otherUser, $post))
            ->toThrow(InvalidArgumentException::class);
    });

});

describe('GetPostByUuidAction', function (): void {

    it('finds post by uuid', function (): void {
        $post = Post::factory()->create();
        $action = app(GetPostByUuidAction::class);

        $found = $action->execute($post->uuid);

        expect($found->id)->toBe($post->id);
    });

    it('returns null for non-existent uuid', function (): void {
        $action = app(GetPostByUuidAction::class);

        $found = $action->execute('00000000-0000-0000-0000-000000000000');

        expect($found)->toBeNull();
    });

    it('loads relations when specified', function (): void {
        $post = Post::factory()->create();
        $action = app(GetPostByUuidAction::class);

        $found = $action->execute($post->uuid, true);

        expect($found->relationLoaded('user'))->toBeTrue();
    });

});

describe('GetFeedAction', function (): void {

    it('returns published posts ordered by published_at', function (): void {
        $oldPost = Post::factory()->publishedAt(now()->subDays(2))->create();
        $newPost = Post::factory()->publishedAt(now()->subDay())->create();
        Post::factory()->draft()->create(); // Should not appear

        $action = app(GetFeedAction::class);
        $feed = $action->execute();

        expect($feed->total())->toBe(2)
            ->and($feed->first()->id)->toBe($newPost->id);
    });

    it('filters by post type', function (): void {
        Post::factory()->create(['type' => PostType::Video]);
        Post::factory()->create(['type' => PostType::ShortClip]);

        $action = app(GetFeedAction::class);
        $feed = $action->execute(type: PostType::Video);

        expect($feed->total())->toBe(1);
    });

    it('prioritizes posts from followed users', function (): void {
        $currentUser = User::factory()->create();
        $followedUser = User::factory()->create();
        $unfollowedUser = User::factory()->create();

        // Current user follows followedUser using Follow model
        \App\Domains\Identity\Models\Follow::create([
            'follower_id' => $currentUser->id,
            'following_id' => $followedUser->id,
            'accepted_at' => now(),
        ]);

        // Create posts with same timestamp
        $followedPost = Post::factory()->forUser($followedUser)->publishedAt(now())->create();
        $unfollowedPost = Post::factory()->forUser($unfollowedUser)->publishedAt(now())->create();

        $action = app(GetFeedAction::class);
        $feed = $action->execute(user: $currentUser);

        // Followed users' posts should appear first
        expect($feed->first()->id)->toBe($followedPost->id);
    });

});

describe('GetUserPostsAction', function (): void {

    it('returns published posts for a user', function (): void {
        $user = User::factory()->create();
        Post::factory()->forUser($user)->count(3)->create();
        Post::factory()->forUser($user)->draft()->create(); // Should not appear

        $action = app(GetUserPostsAction::class);
        $posts = $action->execute($user);

        expect($posts->total())->toBe(3);
    });

    it('includes draft posts when requested', function (): void {
        $user = User::factory()->create();
        Post::factory()->forUser($user)->count(2)->create();
        Post::factory()->forUser($user)->draft()->create();

        $action = app(GetUserPostsAction::class);
        // publishedOnly: false includes all posts
        $posts = $action->execute($user, publishedOnly: false);

        expect($posts->total())->toBe(3);
    });

    // Note: GetUserPostsAction doesn't support type filtering
    // Type filtering is only available via GetFeedAction

});
