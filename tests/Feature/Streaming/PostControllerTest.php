<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Enums\PostType;
use App\Domains\Streaming\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('POST /api/v1/posts', function (): void {

    it('creates a post with youtube url', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/posts', [
                'title' => 'My New Video',
                'description' => 'An awesome video',
                'type' => 'video',
                'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'type',
                    'title',
                    'description',
                    'sourceUrl',
                    'provider',
                    'providerId',
                    'thumbnailUrl',
                    'embedUrl',
                    'isPremium',
                    'isPublished',
                    'publishedAt',
                    'user' => ['id', 'email'],
                    'createdAt',
                ],
            ])
            ->assertJson([
                'data' => [
                    'title' => 'My New Video',
                    'provider' => 'youtube',
                    'providerId' => 'dQw4w9WgXcQ',
                ],
            ]);
    });

    it('creates a post with vimeo url', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/posts', [
                'title' => 'Vimeo Video',
                'type' => 'video',
                'source_url' => 'https://vimeo.com/123456789',
            ]);

        $response->assertCreated()
            ->assertJson([
                'data' => [
                    'provider' => 'vimeo',
                    'providerId' => '123456789',
                ],
            ]);
    });

    it('validates required fields', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/posts', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'source_url']);
    });

    it('validates video url format', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/posts', [
                'title' => 'Test',
                'type' => 'video',
                'source_url' => 'https://dailymotion.com/video/x123',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['source_url']);
    });

    it('requires authentication', function (): void {
        $response = $this->postJson('/api/v1/posts', [
            'title' => 'Test',
            'type' => 'video',
            'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response->assertUnauthorized();
    });

    it('creates a draft post', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/posts', [
                'title' => 'Draft Video',
                'type' => 'video',
                'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'is_published' => false,
            ]);

        $response->assertCreated()
            ->assertJson([
                'data' => [
                    'isPublished' => false,
                    'publishedAt' => null,
                ],
            ]);
    });

});

describe('GET /api/v1/posts/{uuid}', function (): void {

    it('returns a single post', function (): void {
        $post = Post::factory()->youtube()->create([
            'title' => 'Test Post',
            'provider_id' => 'testVideoId',
        ]);

        $response = $this->getJson("/api/v1/posts/{$post->uuid}");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $post->uuid,
                    'title' => 'Test Post',
                    'provider' => 'youtube',
                    'providerId' => 'testVideoId',
                    'embedUrl' => 'https://www.youtube.com/embed/testVideoId',
                ],
            ]);
    });

    it('returns 404 for non-existent post', function (): void {
        $response = $this->getJson('/api/v1/posts/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    });

    it('returns 404 for unpublished post', function (): void {
        $post = Post::factory()->draft()->create();

        $response = $this->getJson("/api/v1/posts/{$post->uuid}");

        $response->assertNotFound();
    });

});

describe('PUT /api/v1/posts/{uuid}', function (): void {

    it('updates own post', function (): void {
        $user = User::factory()->create();
        $post = Post::factory()->forUser($user)->create(['title' => 'Old Title']);

        $response = $this->actingAs($user)
            ->putJson("/api/v1/posts/{$post->uuid}", [
                'title' => 'New Title',
            ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'title' => 'New Title',
                ],
            ]);
    });

    it('cannot update another users post', function (): void {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $post = Post::factory()->forUser($owner)->create();

        $response = $this->actingAs($otherUser)
            ->putJson("/api/v1/posts/{$post->uuid}", [
                'title' => 'Hacked',
            ]);

        $response->assertForbidden();
    });

    it('requires authentication', function (): void {
        $post = Post::factory()->create();

        $response = $this->putJson("/api/v1/posts/{$post->uuid}", [
            'title' => 'New Title',
        ]);

        $response->assertUnauthorized();
    });

});

describe('DELETE /api/v1/posts/{uuid}', function (): void {

    it('deletes own post', function (): void {
        $user = User::factory()->create();
        $post = Post::factory()->forUser($user)->create();

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/posts/{$post->uuid}");

        $response->assertOk()
            ->assertJson(['message' => 'Post deleted successfully.']);
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    });

    it('cannot delete another users post', function (): void {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $post = Post::factory()->forUser($owner)->create();

        $response = $this->actingAs($otherUser)
            ->deleteJson("/api/v1/posts/{$post->uuid}");

        $response->assertForbidden();
        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    });

    it('requires authentication', function (): void {
        $post = Post::factory()->create();

        $response = $this->deleteJson("/api/v1/posts/{$post->uuid}");

        $response->assertUnauthorized();
    });

});

describe('GET /api/v1/feed', function (): void {

    it('returns paginated feed', function (): void {
        Post::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/feed');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'type', 'sourceUrl', 'user'],
                ],
                'meta' => ['currentPage', 'perPage', 'total'],
            ]);
    });

    it('filters feed by type', function (): void {
        Post::factory()->create(['type' => PostType::Video]);
        Post::factory()->create(['type' => PostType::ShortClip]);
        Post::factory()->create(['type' => PostType::Image]);

        $response = $this->getJson('/api/v1/feed?type=video');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('excludes unpublished posts', function (): void {
        Post::factory()->count(2)->create();
        Post::factory()->draft()->create();

        $response = $this->getJson('/api/v1/feed');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('prioritizes followed users for authenticated user', function (): void {
        $currentUser = User::factory()->create();
        $followedUser = User::factory()->create();

        \App\Domains\Identity\Models\Follow::create([
            'follower_id' => $currentUser->id,
            'following_id' => $followedUser->id,
            'accepted_at' => now(),
        ]);

        $followedPost = Post::factory()->forUser($followedUser)->create();
        Post::factory()->count(3)->create();

        $response = $this->actingAs($currentUser)
            ->getJson('/api/v1/feed');

        $response->assertOk();
        // First post should be from followed user
        expect($response->json('data.0.id'))->toBe($followedPost->uuid);
    });

});

describe('GET /api/v1/users/{uuid}/posts', function (): void {

    it('returns posts for a user', function (): void {
        $user = User::factory()->create();
        Post::factory()->forUser($user)->count(3)->create();
        Post::factory()->create(); // Another user's post

        $response = $this->getJson("/api/v1/users/{$user->uuid}/posts");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('excludes unpublished posts', function (): void {
        $user = User::factory()->create();
        Post::factory()->forUser($user)->count(2)->create();
        Post::factory()->forUser($user)->draft()->create();

        $response = $this->getJson("/api/v1/users/{$user->uuid}/posts");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('returns 404 for non-existent user', function (): void {
        $response = $this->getJson('/api/v1/users/00000000-0000-0000-0000-000000000000/posts');

        $response->assertNotFound();
    });

    // Note: Type filtering is not supported by the userPosts endpoint
    // Type filtering is only available via the feed endpoint

});
