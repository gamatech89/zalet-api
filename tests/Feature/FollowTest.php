<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_follow_another_user(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/users/{$targetUser->id}/follow");

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'following' => ['id', 'username'],
            ]);

        $this->assertTrue($user->following()->where('following_id', $targetUser->id)->exists());
    }

    public function test_user_cannot_follow_themselves(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/users/{$user->id}/follow");

        $response->assertStatus(422)
            ->assertJson(['message' => 'You cannot follow yourself.']);
    }

    public function test_user_cannot_follow_same_user_twice(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        $user->following()->attach($targetUser->id);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/users/{$targetUser->id}/follow");

        $response->assertStatus(409)
            ->assertJson(['message' => 'You are already following this user.']);
    }

    public function test_user_can_unfollow_a_user(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        $user->following()->attach($targetUser->id);

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/users/{$targetUser->id}/follow");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Successfully unfollowed user.']);

        $this->assertFalse($user->following()->where('following_id', $targetUser->id)->exists());
    }

    public function test_user_cannot_unfollow_user_not_following(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/users/{$targetUser->id}/follow");

        $response->assertStatus(404)
            ->assertJson(['message' => 'You are not following this user.']);
    }

    public function test_can_get_user_followers(): void
    {
        $user = User::factory()->create();
        $follower1 = User::factory()->create();
        $follower2 = User::factory()->create();

        $user->followers()->attach([$follower1->id, $follower2->id]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/users/{$user->id}/followers");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'username', 'role'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 2);
    }

    public function test_can_get_user_following(): void
    {
        $user = User::factory()->create();
        $following1 = User::factory()->create();
        $following2 = User::factory()->create();

        $user->following()->attach([$following1->id, $following2->id]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/users/{$user->id}/following");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'username', 'role'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 2);
    }

    public function test_follow_requires_authentication(): void
    {
        $targetUser = User::factory()->create();

        $response = $this->postJson("/api/v1/users/{$targetUser->id}/follow");

        $response->assertStatus(401);
    }

    public function test_followers_list_is_paginated(): void
    {
        $user = User::factory()->create();
        $followers = User::factory()->count(25)->create();

        foreach ($followers as $follower) {
            $user->followers()->attach($follower->id);
        }

        $response = $this->actingAs($user)
            ->getJson("/api/v1/users/{$user->id}/followers");

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.last_page', 2);
    }
}
