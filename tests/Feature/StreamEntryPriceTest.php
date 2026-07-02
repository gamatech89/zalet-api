<?php

namespace Tests\Feature;

use App\Models\LiveStream;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CoinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreamEntryPriceTest extends TestCase
{
    use RefreshDatabase;

    private function giveActiveSubscription(User $user, int $level): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Test Plan Level ' . $level,
            'slug' => 'test-plan-' . $level . '-' . $user->id,
            'level' => $level,
            'price_monthly' => 500,
        ]);

        Subscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'price_paid' => 500,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);
    }

    public function test_purchase_stream_entry_transfers_full_price_to_streamer(): void
    {
        $streamer = User::factory()->create();
        $buyer = User::factory()->create();
        $stream = LiveStream::factory()->live()->create(['user_id' => $streamer->id, 'entry_price' => 80]);

        $coinService = app(CoinService::class);
        $coinService->credit($buyer, 200, 'test funding');

        $transaction = $coinService->purchaseStreamEntry($buyer, $stream);

        $this->assertEquals(80, (float) $transaction->amount);
        $this->assertEquals('stream_entry', $transaction->type);
        $this->assertEquals(120, (float) Wallet::where('user_id', $buyer->id)->first()->balance);
        $this->assertEquals(80, (float) Wallet::where('user_id', $streamer->id)->first()->balance);
    }

    public function test_purchase_stream_entry_throws_on_insufficient_balance(): void
    {
        $streamer = User::factory()->create();
        $buyer = User::factory()->create();
        $stream = LiveStream::factory()->live()->create(['user_id' => $streamer->id, 'entry_price' => 80]);

        $this->expectException(\RuntimeException::class);
        app(CoinService::class)->purchaseStreamEntry($buyer, $stream);
    }

    public function test_show_stream_includes_entry_price_and_lock_state(): void
    {
        $streamer = User::factory()->create();
        $viewer = User::factory()->create();
        $stream = LiveStream::factory()->live()->create(['user_id' => $streamer->id, 'entry_price' => 60]);

        $response = $this->actingAs($viewer)->getJson("/api/v1/streams/{$stream->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.entry_price', 60)
            ->assertJsonPath('data.is_unlocked', false);
    }

    public function test_viewer_token_denied_when_stream_locked(): void
    {
        $streamer = User::factory()->create();
        $viewer = User::factory()->create();
        $stream = LiveStream::factory()->live()->create(['user_id' => $streamer->id, 'entry_price' => 60]);

        $response = $this->actingAs($viewer)->getJson("/api/v1/streams/{$stream->id}/token");

        $response->assertStatus(403);
    }

    public function test_unlock_endpoint_grants_viewer_token_access(): void
    {
        $streamer = User::factory()->create();
        $viewer = User::factory()->create();
        $stream = LiveStream::factory()->live()->create(['user_id' => $streamer->id, 'entry_price' => 60]);
        app(CoinService::class)->credit($viewer, 100, 'test funding');

        $unlockResponse = $this->actingAs($viewer)->postJson("/api/v1/streams/{$stream->id}/unlock");
        $unlockResponse->assertStatus(200);
        $this->assertDatabaseHas('stream_unlocks', ['user_id' => $viewer->id, 'live_stream_id' => $stream->id]);

        $tokenResponse = $this->actingAs($viewer)->getJson("/api/v1/streams/{$stream->id}/token");
        $tokenResponse->assertStatus(200);
    }

    public function test_subscribed_user_bypasses_stream_entry_price(): void
    {
        $streamer = User::factory()->create();
        $viewer = User::factory()->create();
        $this->giveActiveSubscription($viewer, 2);
        $stream = LiveStream::factory()->live()->create(['user_id' => $streamer->id, 'entry_price' => 60]);

        $response = $this->actingAs($viewer)->getJson("/api/v1/streams/{$stream->id}/token");

        $response->assertStatus(200);
    }

    public function test_owner_is_never_locked_out_of_own_stream(): void
    {
        $streamer = User::factory()->create();
        $stream = LiveStream::factory()->live()->create(['user_id' => $streamer->id, 'entry_price' => 60]);

        $response = $this->actingAs($streamer)->getJson("/api/v1/streams/{$stream->id}");

        $response->assertStatus(200)->assertJsonPath('data.is_unlocked', true);
    }
}
