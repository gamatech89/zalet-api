<?php

namespace Tests\Feature;

use App\Models\CreatorRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatorRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test submit a creator request.
     */
    public function test_submit_creator_request(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/creator-requests', [
                'message' => 'I want to create content about music.',
                'portfolio_url' => 'https://example.com/portfolio',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.message', 'I want to create content about music.');

        $this->assertDatabaseHas('creator_requests', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Test cannot submit request when already creator.
     */
    public function test_creator_cannot_submit_request(): void
    {
        $creator = User::factory()->create(['role' => 'creator']);

        $response = $this->actingAs($creator, 'sanctum')
            ->postJson('/api/v1/creator-requests', [
                'message' => 'I am already a creator.',
            ]);

        $response->assertStatus(400);
    }

    /**
     * Test cannot submit duplicate pending request.
     */
    public function test_cannot_submit_duplicate_pending_request(): void
    {
        $user = User::factory()->create();

        CreatorRequest::create([
            'user_id' => $user->id,
            'message' => 'First request',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/creator-requests', [
                'message' => 'Second request',
            ]);

        $response->assertStatus(409);
    }

    /**
     * Test get own creator request status.
     */
    public function test_get_own_request_status(): void
    {
        $user = User::factory()->create();

        CreatorRequest::create([
            'user_id' => $user->id,
            'message' => 'My request',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/creator-requests/mine');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.message', 'My request');
    }

    /**
     * Test get request when none exists.
     */
    public function test_get_request_when_none_exists(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/creator-requests/mine');

        $response->assertStatus(200)
            ->assertJsonPath('data', null);
    }

    /**
     * Test approve a creator request promotes user.
     */
    public function test_approve_creator_request(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);

        $request = CreatorRequest::create([
            'user_id' => $user->id,
            'message' => 'Promote me',
            'status' => 'pending',
        ]);

        $request->approve($admin, 'Welcome aboard!');

        $user->refresh();
        $request->refresh();

        $this->assertEquals('creator', $user->role);
        $this->assertEquals('approved', $request->status);
        $this->assertEquals('Welcome aboard!', $request->admin_notes);
        $this->assertEquals($admin->id, $request->reviewed_by);
    }

    /**
     * Test reject a creator request.
     */
    public function test_reject_creator_request(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);

        $request = CreatorRequest::create([
            'user_id' => $user->id,
            'message' => 'Promote me',
            'status' => 'pending',
        ]);

        $request->reject($admin, 'Not enough portfolio.');

        $user->refresh();
        $request->refresh();

        $this->assertEquals('user', $user->role);
        $this->assertEquals('rejected', $request->status);
        $this->assertEquals('Not enough portfolio.', $request->admin_notes);
    }
}
