<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchievementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->user = User::factory()->create(['role' => 'user']);
    }

    private function makePayload(string $name = 'Test', array $tiers = []): array
    {
        return [
            'name' => $name,
            'description' => null,
            'icon' => null,
            'event_type' => 'message_sent',
            'aggregation' => ['type' => 'count', 'criteria' => []],
            'tiers' => $tiers ?: [['threshold' => 10]],
        ];
    }

    // ── Admin CRUD ──

    public function test_admin_can_create_achievement(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/achievements', [
                'name' => 'Chatterbox',
                'description' => 'Send messages',
                'icon' => 'chat',
                'event_type' => 'message_sent',
                'aggregation' => ['type' => 'count', 'criteria' => []],
                'tiers' => [
                    ['threshold' => 10],
                    ['threshold' => 50, 'reward' => ['type' => 'coins', 'amount' => 100]],
                    ['threshold' => 200, 'reward' => ['type' => 'coins', 'amount' => 500]],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Chatterbox')
            ->assertJsonCount(3, 'data.tiers');

        $this->assertDatabaseHas('achievements', ['name' => 'Chatterbox']);
        $this->assertDatabaseCount('achievement_tiers', 3);
    }

    public function test_tiers_get_auto_leveled(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/achievements', $this->makePayload('Test', [
                ['threshold' => 10],
                ['threshold' => 50],
                ['threshold' => 200],
            ]));

        $response->assertStatus(201);

        $tiers = $response->json('data.tiers');
        $this->assertEquals(1, $tiers[0]['level']);
        $this->assertEquals(2, $tiers[1]['level']);
        $this->assertEquals(3, $tiers[2]['level']);
    }

    public function test_create_fails_with_descending_thresholds(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/achievements', $this->makePayload('Bad', [
                ['threshold' => 50],
                ['threshold' => 10],
            ]));

        $response->assertStatus(422);
    }

    public function test_admin_can_list_achievements(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/achievements', $this->makePayload('A'));
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/achievements', $this->makePayload('B'));

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/achievements');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_update_and_sync_tiers(): void
    {
        $create = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/achievements', $this->makePayload('Old', [
                ['threshold' => 10],
                ['threshold' => 50],
                ['threshold' => 200],
            ]));

        $id = $create->json('data.id');
        $tier1Id = $create->json('data.tiers.0.id');
        $tier3Id = $create->json('data.tiers.2.id');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/achievements/{$id}", [
                'name' => 'New',
                'tiers' => [
                    ['id' => $tier1Id, 'threshold' => 15],
                    ['id' => $tier3Id, 'threshold' => 100],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New')
            ->assertJsonCount(2, 'data.tiers');

        $tiers = $response->json('data.tiers');
        $this->assertEquals(1, $tiers[0]['level']);
        $this->assertEquals(2, $tiers[1]['level']);
        $this->assertDatabaseCount('achievement_tiers', 2);
    }

    public function test_admin_can_delete_achievement(): void
    {
        $create = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/achievements', $this->makePayload('Del'));

        $id = $create->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/achievements/{$id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('achievements', ['id' => $id]);
        $this->assertDatabaseCount('achievement_tiers', 0);
    }

    public function test_non_admin_cannot_access(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/admin/achievements')
            ->assertStatus(403);
    }

    public function test_invalid_aggregation_rejected(): void
    {
        $payload = $this->makePayload('Bad');
        $payload['aggregation'] = ['type' => 'invalid', 'criteria' => []];

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/achievements', $payload)
            ->assertStatus(422);
    }

    // ── User ──

    public function test_user_can_view_achievements(): void
    {
        $achievement = Achievement::create([
            'name' => 'Chatterbox', 'description' => 'Send messages', 'icon' => 'chat',
            'event_type' => 'message_sent', 'aggregation' => ['type' => 'count', 'criteria' => []],
        ]);
        $achievement->tiers()->create(['level' => 1, 'threshold' => 10]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/achievements');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Chatterbox')
            ->assertJsonPath('data.0.state', 'in_progress');
    }

    public function test_disabled_achievements_hidden(): void
    {
        $achievement = Achievement::create([
            'name' => 'Disabled', 'description' => null, 'icon' => null,
            'event_type' => 'message_sent', 'aggregation' => ['type' => 'count', 'criteria' => []],
            'is_enabled' => false,
        ]);
        $achievement->tiers()->create(['level' => 1, 'threshold' => 10]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/achievements')
            ->assertJsonCount(0, 'data');
    }

    public function test_claimable_state(): void
    {
        $achievement = Achievement::create([
            'name' => 'Test', 'description' => null, 'icon' => null,
            'event_type' => 'message_sent', 'aggregation' => ['type' => 'count', 'criteria' => []],
        ]);
        $tier = $achievement->tiers()->create([
            'level' => 1, 'threshold' => 5, 'reward' => ['type' => 'coins', 'amount' => 100],
        ]);
        $tier->users()->attach($this->user->id, ['progress' => 5, 'unlocked_at' => now()]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/achievements')
            ->assertJsonPath('data.0.state', 'claimable');
    }

    public function test_completed_state(): void
    {
        $achievement = Achievement::create([
            'name' => 'Test', 'description' => null, 'icon' => null,
            'event_type' => 'message_sent', 'aggregation' => ['type' => 'count', 'criteria' => []],
        ]);
        $tier = $achievement->tiers()->create(['level' => 1, 'threshold' => 5]);
        $tier->users()->attach($this->user->id, ['progress' => 5, 'unlocked_at' => now()]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/achievements')
            ->assertJsonPath('data.0.state', 'completed');
    }

    // ── Collect ──

    public function test_collect_reward(): void
    {
        Wallet::create(['user_id' => $this->user->id, 'balance' => 0]);

        $achievement = Achievement::create([
            'name' => 'Test', 'description' => null, 'icon' => null,
            'event_type' => 'message_sent', 'aggregation' => ['type' => 'count', 'criteria' => []],
        ]);
        $tier = $achievement->tiers()->create([
            'level' => 1, 'threshold' => 5, 'reward' => ['type' => 'coins', 'amount' => 100],
        ]);
        $tier->users()->attach($this->user->id, ['progress' => 5, 'unlocked_at' => now()]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/achievements/{$tier->id}/collect")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Reward collected!');

        $this->assertNotNull($tier->users()->where('user_id', $this->user->id)->first()->pivot->collected_at);
    }

    public function test_cannot_collect_twice(): void
    {
        $achievement = Achievement::create([
            'name' => 'Test', 'description' => null, 'icon' => null,
            'event_type' => 'message_sent', 'aggregation' => ['type' => 'count', 'criteria' => []],
        ]);
        $tier = $achievement->tiers()->create([
            'level' => 1, 'threshold' => 5, 'reward' => ['type' => 'coins', 'amount' => 100],
        ]);
        $tier->users()->attach($this->user->id, ['progress' => 5, 'unlocked_at' => now(), 'collected_at' => now()]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/achievements/{$tier->id}/collect")
            ->assertStatus(422);
    }

    public function test_cannot_collect_locked(): void
    {
        $achievement = Achievement::create([
            'name' => 'Test', 'description' => null, 'icon' => null,
            'event_type' => 'message_sent', 'aggregation' => ['type' => 'count', 'criteria' => []],
        ]);
        $tier = $achievement->tiers()->create([
            'level' => 1, 'threshold' => 5, 'reward' => ['type' => 'coins', 'amount' => 100],
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/achievements/{$tier->id}/collect")
            ->assertStatus(422);
    }

    public function test_cannot_collect_no_reward(): void
    {
        $achievement = Achievement::create([
            'name' => 'Test', 'description' => null, 'icon' => null,
            'event_type' => 'message_sent', 'aggregation' => ['type' => 'count', 'criteria' => []],
        ]);
        $tier = $achievement->tiers()->create(['level' => 1, 'threshold' => 5]);
        $tier->users()->attach($this->user->id, ['progress' => 5, 'unlocked_at' => now()]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/achievements/{$tier->id}/collect")
            ->assertStatus(422);
    }
}
