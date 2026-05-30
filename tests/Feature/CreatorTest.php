<?php

namespace Tests\Feature;

use App\Models\LiveStream;
use App\Models\Media;
use App\Models\StreamSession;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatorTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Authorization Tests
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_user_cannot_access_creator_routes(): void
    {
        $response = $this->getJson('/api/v1/creator/stats');
        $response->assertStatus(401);
    }

    public function test_regular_user_cannot_access_creator_routes(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->getJson('/api/v1/creator/stats');
        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden. Creator access required.']);
    }

    public function test_creator_can_access_creator_routes(): void
    {
        $creator = User::factory()->create(['role' => 'creator']);

        $response = $this->actingAs($creator)->getJson('/api/v1/creator/stats');
        $response->assertStatus(200);
    }

    public function test_admin_can_access_creator_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->getJson('/api/v1/creator/stats');
        $response->assertStatus(200);
    }

    /*
    |--------------------------------------------------------------------------
    | Stats Endpoint Tests
    |--------------------------------------------------------------------------
    */

    public function test_stats_returns_correct_structure(): void
    {
        $creator = User::factory()->create(['role' => 'creator']);

        $response = $this->actingAs($creator)->getJson('/api/v1/creator/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'earnings' => ['total', 'this_month'],
                    'subscribers' => ['active', 'total'],
                    'followers',
                    'content' => ['total', 'moments', 'cinema'],
                    'streams' => ['total', 'total_earnings'],
                ],
            ]);
    }

    public function test_stats_reflects_actual_data(): void
    {
        $creator = User::factory()->create(['role' => 'creator']);
        $wallet = Wallet::factory()->create(['user_id' => $creator->id]);

        // Create content
        Media::factory()->count(2)->create(['user_id' => $creator->id, 'type' => 'moment']);
        Media::factory()->create(['user_id' => $creator->id, 'type' => 'embed']);

        $response = $this->actingAs($creator)->getJson('/api/v1/creator/stats');

        $response->assertStatus(200)
            ->assertJsonPath('data.subscribers.total', 0)
            ->assertJsonPath('data.content.total', 3)
            ->assertJsonPath('data.content.moments', 2)
            ->assertJsonPath('data.content.cinema', 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Earnings Endpoint Tests
    |--------------------------------------------------------------------------
    */

    public function test_earnings_returns_breakdown(): void
    {
        $creator = User::factory()->create(['role' => 'creator']);
        $wallet = Wallet::factory()->create(['user_id' => $creator->id]);
        $senderWallet = Wallet::factory()->create();

        // Create tip transaction
        Transaction::create([
            'from_wallet_id' => $senderWallet->id,
            'to_wallet_id' => $wallet->id,
            'amount' => 50,
            'type' => 'tip',
            'status' => 'completed',
        ]);

        // Create PPV transaction
        Transaction::create([
            'from_wallet_id' => $senderWallet->id,
            'to_wallet_id' => $wallet->id,
            'amount' => 100,
            'type' => 'ppv',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($creator)->getJson('/api/v1/creator/earnings');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(50, $data['tips']);
        $this->assertEquals(100, $data['ppv']);
        $this->assertEquals(150, $data['total']);
    }

    public function test_earnings_can_filter_by_date(): void
    {
        $creator = User::factory()->create(['role' => 'creator']);
        $wallet = Wallet::factory()->create(['user_id' => $creator->id]);
        $senderWallet = Wallet::factory()->create();

        $today = now()->format('Y-m-d');

        Transaction::create([
            'from_wallet_id' => $senderWallet->id,
            'to_wallet_id' => $wallet->id,
            'amount' => 100,
            'type' => 'tip',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($creator)
            ->getJson("/api/v1/creator/earnings?from_date={$today}&to_date={$today}");

        $response->assertStatus(200);
        $this->assertEquals(100, $response->json('data.total'));
    }

    /*
    |--------------------------------------------------------------------------
    | Subscribers Endpoint Tests
    |--------------------------------------------------------------------------
    */

    public function test_subscribers_returns_empty_while_deferred(): void
    {
        $creator = User::factory()->create(['role' => 'creator']);

        $response = $this->actingAs($creator)->getJson('/api/v1/creator/subscribers');

        $response->assertStatus(200)
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Content Endpoint Tests
    |--------------------------------------------------------------------------
    */

    public function test_content_returns_media_with_stats(): void
    {
        $creator = User::factory()->create(['role' => 'creator']);
        
        $media = Media::factory()->create([
            'user_id' => $creator->id,
            'is_ppv' => true,
            'price_coins' => 50,
        ]);

        $response = $this->actingAs($creator)->getJson('/api/v1/creator/content');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'type', 'is_ppv', 'price_coins', 'created_at', 'stats'],
                ],
                'meta',
            ])
            ->assertJsonPath('meta.total', 1);
    }

    public function test_content_can_filter_by_type(): void
    {
        $creator = User::factory()->create(['role' => 'creator']);
        
        Media::factory()->count(2)->create(['user_id' => $creator->id, 'type' => 'moment']);
        Media::factory()->create(['user_id' => $creator->id, 'type' => 'embed']);

        $response = $this->actingAs($creator)->getJson('/api/v1/creator/content?type=moment');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    /*
    |--------------------------------------------------------------------------
    | Stream History Tests
    |--------------------------------------------------------------------------
    */

    public function test_stream_history_returns_past_sessions(): void
    {
        $creator = User::factory()->create(['role' => 'creator']);
        $stream = LiveStream::factory()->create(['user_id' => $creator->id]);
        
        StreamSession::create([
            'live_stream_id' => $stream->id,
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHour(),
            'peak_viewers' => 50,
            'total_coins_collected' => 100,
        ]);

        $response = $this->actingAs($creator)->getJson('/api/v1/creator/streams/history');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'start_time', 'end_time', 'duration_minutes', 'peak_viewers', 'coins_collected'],
                ],
                'meta',
            ])
            ->assertJsonPath('meta.total', 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Analytics Tests
    |--------------------------------------------------------------------------
    */

    public function test_analytics_returns_time_series_data(): void
    {
        $creator = User::factory()->create(['role' => 'creator']);

        $response = $this->actingAs($creator)->getJson('/api/v1/creator/analytics?days=7');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'period_days',
                    'series' => [
                        '*' => ['date', 'earnings', 'new_subscribers', 'new_followers'],
                    ],
                ],
            ])
            ->assertJsonCount(7, 'data.series');
        
        $this->assertEquals(7, $response->json('data.period_days'));
    }

    /*
    |--------------------------------------------------------------------------
    | Top Supporters Tests
    |--------------------------------------------------------------------------
    */

    public function test_top_supporters_returns_ranked_list(): void
    {
        $creator = User::factory()->create(['role' => 'creator']);
        $creatorWallet = Wallet::factory()->create(['user_id' => $creator->id]);

        $supporter1 = User::factory()->create();
        $supporter1Wallet = Wallet::factory()->create(['user_id' => $supporter1->id]);

        $supporter2 = User::factory()->create();
        $supporter2Wallet = Wallet::factory()->create(['user_id' => $supporter2->id]);

        // Supporter 1 sends 100 total
        Transaction::create([
            'from_wallet_id' => $supporter1Wallet->id,
            'to_wallet_id' => $creatorWallet->id,
            'amount' => 100,
            'type' => 'tip',
            'status' => 'completed',
        ]);

        // Supporter 2 sends 50 total
        Transaction::create([
            'from_wallet_id' => $supporter2Wallet->id,
            'to_wallet_id' => $creatorWallet->id,
            'amount' => 50,
            'type' => 'tip',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($creator)->getJson('/api/v1/creator/top-supporters');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
        
        // First should be supporter1 with higher amount
        $data = $response->json('data');
        $this->assertEquals(100.0, $data[0]['total_amount']);
        $this->assertEquals(50.0, $data[1]['total_amount']);
    }

    public function test_top_supporters_respects_limit(): void
    {
        $creator = User::factory()->create(['role' => 'creator']);
        $creatorWallet = Wallet::factory()->create(['user_id' => $creator->id]);

        // Create 5 supporters
        for ($i = 1; $i <= 5; $i++) {
            $supporter = User::factory()->create();
            $supporterWallet = Wallet::factory()->create(['user_id' => $supporter->id]);
            Transaction::create([
                'from_wallet_id' => $supporterWallet->id,
                'to_wallet_id' => $creatorWallet->id,
                'amount' => $i * 10,
                'type' => 'tip',
                'status' => 'completed',
            ]);
        }

        $response = $this->actingAs($creator)->getJson('/api/v1/creator/top-supporters?limit=3');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }
}
