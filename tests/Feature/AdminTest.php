<?php

namespace Tests\Feature;

use App\Models\LiveStream;
use App\Models\Media;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Authorization Tests
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_user_cannot_access_admin_routes(): void
    {
        $response = $this->getJson('/api/v1/admin/stats');
        $response->assertStatus(401);
    }

    public function test_regular_user_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->getJson('/api/v1/admin/stats');
        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden. Admin access required.']);
    }

    public function test_creator_cannot_access_admin_routes(): void
    {
        $creator = User::factory()->create(['role' => 'creator']);

        $response = $this->actingAs($creator)->getJson('/api/v1/admin/stats');
        $response->assertStatus(403);
    }

    public function test_admin_can_access_admin_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/stats');
        $response->assertStatus(200);
    }

    /*
    |--------------------------------------------------------------------------
    | Stats Endpoint Tests
    |--------------------------------------------------------------------------
    */

    public function test_stats_returns_correct_structure(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(3)->create(['role' => 'user']);
        User::factory()->count(2)->create(['role' => 'creator']);
        User::factory()->create(['role' => 'user', 'is_legacy_founder' => true]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'users' => ['total', 'admins', 'creators', 'regular', 'legacy_founders'],
                    'transactions' => ['total', 'total_volume', 'deposits', 'withdrawals'],
                    'content' => ['media_total', 'moments', 'cinema', 'long_form'],
                    'streams' => ['total', 'currently_live'],
                ],
            ]);
    }

    public function test_stats_returns_correct_user_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(5)->create(['role' => 'user']);
        User::factory()->count(3)->create(['role' => 'creator']);
        User::factory()->count(2)->create(['role' => 'user', 'is_legacy_founder' => true]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/stats');

        $response->assertStatus(200)
            ->assertJsonPath('data.users.total', 11) // 1 admin + 5 users + 3 creators + 2 founders
            ->assertJsonPath('data.users.admins', 1)
            ->assertJsonPath('data.users.creators', 3)
            ->assertJsonPath('data.users.regular', 7) // 5 + 2 (founders are also 'user' role)
            ->assertJsonPath('data.users.legacy_founders', 2);
    }

    /*
    |--------------------------------------------------------------------------
    | User Management Tests
    |--------------------------------------------------------------------------
    */

    public function test_list_users_returns_paginated_results(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(25)->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 26) // 25 + 1 admin
            ->assertJsonPath('meta.per_page', 20);
    }

    public function test_list_users_can_filter_by_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(5)->create(['role' => 'user']);
        User::factory()->count(3)->create(['role' => 'creator']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/users?role=creator');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_list_users_can_search_by_email(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@zalet.test']);
        $targetUser = User::factory()->create(['email' => 'uniquesearchtest123@example.com']);
        User::factory()->create(['email' => 'other@example.com']);
        User::factory()->count(5)->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/users?search=uniquesearchtest123');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_list_users_can_filter_by_legacy_founder(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(3)->create(['is_legacy_founder' => true]);
        User::factory()->count(5)->create(['is_legacy_founder' => false]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/users?is_legacy_founder=true');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_update_user_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/admin/users/{$user->id}", ['role' => 'creator']);

        $response->assertStatus(200)
            ->assertJsonPath('data.role', 'creator');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'creator',
        ]);
    }

    public function test_update_user_validates_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/admin/users/{$user->id}", ['role' => 'invalid_role']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_mark_user_as_founder(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['is_legacy_founder' => false]);

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/admin/users/{$user->id}/founder");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_legacy_founder', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_legacy_founder' => true,
        ]);
    }

    public function test_cannot_mark_existing_founder_again(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $founder = User::factory()->create(['is_legacy_founder' => true]);

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/admin/users/{$founder->id}/founder");

        $response->assertStatus(409)
            ->assertJson(['message' => 'User is already a legacy founder.']);
    }

    /*
    |--------------------------------------------------------------------------
    | Transaction Tests
    |--------------------------------------------------------------------------
    */

    public function test_list_transactions_returns_paginated_results(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create(['user_id' => $user->id]);
        // Create transactions manually since no factory exists
        for ($i = 0; $i < 5; $i++) {
            Transaction::create([
                'to_wallet_id' => $wallet->id,
                'amount' => 100,
                'type' => 'deposit',
                'status' => 'completed',
            ]);
        }

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/transactions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 5);
    }

    public function test_list_transactions_can_filter_by_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create(['user_id' => $user->id]);
        // Create deposits
        for ($i = 0; $i < 3; $i++) {
            Transaction::create([
                'to_wallet_id' => $wallet->id,
                'amount' => 100,
                'type' => 'deposit',
                'status' => 'completed',
            ]);
        }
        // Create tips
        for ($i = 0; $i < 2; $i++) {
            Transaction::create([
                'to_wallet_id' => $wallet->id,
                'amount' => 50,
                'type' => 'tip',
                'status' => 'completed',
            ]);
        }

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/transactions?type=deposit');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }

    /*
    |--------------------------------------------------------------------------
    | Media Moderation Tests
    |--------------------------------------------------------------------------
    */

    public function test_list_media_returns_paginated_results(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $creator = User::factory()->create(['role' => 'creator']);
        Media::factory()->count(5)->create(['user_id' => $creator->id]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/media');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 5);
    }

    public function test_list_media_can_filter_by_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $creator = User::factory()->create(['role' => 'creator']);
        Media::factory()->count(3)->create(['user_id' => $creator->id, 'type' => 'moment']);
        Media::factory()->count(2)->create(['user_id' => $creator->id, 'type' => 'embed']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/media?type=moment');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_delete_media(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $creator = User::factory()->create(['role' => 'creator']);
        $media = Media::factory()->create(['user_id' => $creator->id]);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/media/{$media->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Media deleted successfully.']);

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Stream Management Tests
    |--------------------------------------------------------------------------
    */

    public function test_list_streams_returns_paginated_results(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $creator = User::factory()->create(['role' => 'creator']);
        LiveStream::factory()->count(5)->create(['user_id' => $creator->id]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/streams');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 5);
    }

    public function test_list_streams_can_filter_by_live_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $creator = User::factory()->create(['role' => 'creator']);
        LiveStream::factory()->count(3)->create(['user_id' => $creator->id, 'is_live' => true]);
        LiveStream::factory()->count(2)->create(['user_id' => $creator->id, 'is_live' => false]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/streams?is_live=true');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_force_end_stream(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $creator = User::factory()->create(['role' => 'creator']);
        $stream = LiveStream::factory()->create([
            'user_id' => $creator->id,
            'is_live' => true,
        ]);
        $stream->goLive();

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/admin/streams/{$stream->id}/end");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Stream ended successfully.']);

        $this->assertDatabaseHas('live_streams', [
            'id' => $stream->id,
            'is_live' => false,
        ]);
    }

    public function test_cannot_end_stream_not_live(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $creator = User::factory()->create(['role' => 'creator']);
        $stream = LiveStream::factory()->create([
            'user_id' => $creator->id,
            'is_live' => false,
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/admin/streams/{$stream->id}/end");

        $response->assertStatus(409)
            ->assertJson(['message' => 'Stream is not currently live.']);
    }
}
