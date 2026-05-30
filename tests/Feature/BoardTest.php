<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardPost;
use App\Models\BoardPostComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Board $board;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
        $this->user->wallet()->create(['balance' => 10]);

        $this->board = Board::create([
            'name'         => 'Wien',
            'slug'         => 'wien',
            'country_code' => 'AT',
            'city'         => 'Vienna',
            'description'  => 'Test board',
            'is_active'    => true,
        ]);
    }

    // === Board Listing ===

    public function test_can_list_boards(): void
    {
        Board::create(['name' => 'Berlin', 'slug' => 'berlin', 'country_code' => 'DE', 'is_active' => true]);

        $response = $this->getJson('/api/v1/boards');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Berlin') // alphabetical
            ->assertJsonPath('data.1.name', 'Wien');
    }

    public function test_can_filter_boards_by_country(): void
    {
        Board::create(['name' => 'Berlin', 'slug' => 'berlin', 'country_code' => 'DE', 'is_active' => true]);

        $response = $this->getJson('/api/v1/boards?country=AT');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Wien');
    }

    public function test_can_search_boards(): void
    {
        Board::create(['name' => 'Berlin', 'slug' => 'berlin', 'country_code' => 'DE', 'is_active' => true]);

        $response = $this->getJson('/api/v1/boards?q=wien');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_can_get_single_board(): void
    {
        $response = $this->getJson('/api/v1/boards/wien');

        $response->assertOk()
            ->assertJsonPath('data.name', 'Wien')
            ->assertJsonPath('data.country_code', 'AT');
    }

    // === Board Posts ===

    public function test_can_list_posts_in_board(): void
    {
        BoardPost::create([
            'board_id' => $this->board->id,
            'user_id' => $this->user->id,
            'title' => 'Test Post',
            'body' => 'Test body content',
            'category' => 'apartment',
            'type' => 'offer',
        ]);

        $response = $this->getJson('/api/v1/boards/wien/posts');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Test Post')
            ->assertJsonPath('data.0.category', 'apartment');
    }

    public function test_can_filter_posts_by_category(): void
    {
        BoardPost::create([
            'board_id' => $this->board->id,
            'user_id' => $this->user->id,
            'title' => 'Apartment',
            'body' => 'Looking for flat',
            'category' => 'apartment',
        ]);
        BoardPost::create([
            'board_id' => $this->board->id,
            'user_id' => $this->user->id,
            'title' => 'Job',
            'body' => 'Looking for work',
            'category' => 'job',
        ]);

        $response = $this->getJson('/api/v1/boards/wien/posts?category=apartment');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', 'apartment');
    }

    public function test_can_create_post_authenticated(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v1/boards/wien/posts', [
            'title' => 'New Post',
            'body' => 'Post body here',
            'category' => 'general',
            'type' => 'question',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'New Post')
            ->assertJsonPath('data.category', 'general')
            ->assertJsonPath('data.type', 'question');

        $this->assertDatabaseHas('board_posts', [
            'title' => 'New Post',
            'board_id' => $this->board->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_cannot_create_post_unauthenticated(): void
    {
        $response = $this->postJson('/api/v1/boards/wien/posts', [
            'title' => 'New Post',
            'body' => 'Body',
            'category' => 'general',
        ]);

        $response->assertUnauthorized();
    }

    public function test_create_post_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v1/boards/wien/posts', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'body', 'category']);
    }

    public function test_can_view_single_post(): void
    {
        $post = BoardPost::create([
            'board_id' => $this->board->id,
            'user_id' => $this->user->id,
            'title' => 'Detail Post',
            'body' => 'Full body content',
            'category' => 'advice',
        ]);

        $response = $this->getJson("/api/v1/boards/wien/posts/{$post->id}");

        $response->assertOk()
            ->assertJsonPath('data.title', 'Detail Post')
            ->assertJsonPath('data.views_count', 1); // auto-incremented
    }

    public function test_can_delete_own_post(): void
    {
        $post = BoardPost::create([
            'board_id' => $this->board->id,
            'user_id' => $this->user->id,
            'title' => 'To Delete',
            'body' => 'Will be deleted',
            'category' => 'general',
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v1/boards/wien/posts/{$post->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('board_posts', ['id' => $post->id]);
    }

    public function test_cannot_delete_others_post(): void
    {
        $otherUser = User::factory()->create();
        $post = BoardPost::create([
            'board_id' => $this->board->id,
            'user_id' => $otherUser->id,
            'title' => 'Other Post',
            'body' => 'Not yours',
            'category' => 'general',
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v1/boards/wien/posts/{$post->id}");

        $response->assertForbidden();
    }

    // === Likes ===

    public function test_can_toggle_like(): void
    {
        $post = BoardPost::create([
            'board_id' => $this->board->id,
            'user_id' => $this->user->id,
            'title' => 'Likeable',
            'body' => 'Like me',
            'category' => 'general',
        ]);

        // Like
        $response = $this->withToken($this->token)
            ->postJson("/api/v1/boards/wien/posts/{$post->id}/like");

        $response->assertOk()
            ->assertJsonPath('liked', true)
            ->assertJsonPath('likes_count', 1);

        // Unlike
        $response = $this->withToken($this->token)
            ->postJson("/api/v1/boards/wien/posts/{$post->id}/like");

        $response->assertOk()
            ->assertJsonPath('liked', false)
            ->assertJsonPath('likes_count', 0);
    }

    // === Comments ===

    public function test_can_add_comment(): void
    {
        $post = BoardPost::create([
            'board_id' => $this->board->id,
            'user_id' => $this->user->id,
            'title' => 'Commentable',
            'body' => 'Comment me',
            'category' => 'general',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v1/boards/wien/posts/{$post->id}/comments", [
            'body' => 'Great post!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.body', 'Great post!');

        $this->assertDatabaseHas('board_post_comments', [
            'post_id' => $post->id,
            'user_id' => $this->user->id,
            'body' => 'Great post!',
        ]);
    }

    public function test_comment_requires_body(): void
    {
        $post = BoardPost::create([
            'board_id' => $this->board->id,
            'user_id' => $this->user->id,
            'title' => 'Commentable',
            'body' => 'x',
            'category' => 'general',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v1/boards/wien/posts/{$post->id}/comments", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }
}