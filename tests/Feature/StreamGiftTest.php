<?php

namespace Tests\Feature;

use App\Models\Gift;
use App\Models\LiveStream;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreamGiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_send_gift_to_live_stream(): void
    {
        $sender = User::factory()->create();
        $streamer = User::factory()->create(['role' => 'creator']);

        // Create wallets
        Wallet::factory()->create(['user_id' => $sender->id, 'balance' => 100]);
        Wallet::factory()->create(['user_id' => $streamer->id, 'balance' => 0]);

        // Create gift
        $gift = Gift::factory()->create(['coin_price' => 10]);

        // Create live stream with active session
        $stream = LiveStream::factory()->create([
            'user_id' => $streamer->id,
            'is_live' => true,
        ]);
        $session = $stream->goLive();

        $response = $this->actingAs($sender)
            ->postJson("/api/v1/streams/{$stream->id}/gift", [
                'gift_id' => $gift->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => ['gift', 'transaction_id', 'session_total'],
            ]);

        // Verify balances updated
        $this->assertEquals(90, $sender->wallet->fresh()->balance);
        $this->assertEquals(10, $streamer->wallet->fresh()->balance);

        // Verify session total updated
        $this->assertEquals(10, $session->fresh()->total_coins_collected);
    }

    public function test_cannot_gift_offline_stream(): void
    {
        $sender = User::factory()->create();
        Wallet::factory()->create(['user_id' => $sender->id, 'balance' => 100]);

        $streamer = User::factory()->create(['role' => 'creator']);
        $gift = Gift::factory()->create(['coin_price' => 10]);

        $stream = LiveStream::factory()->create([
            'user_id' => $streamer->id,
            'is_live' => false,
        ]);

        $response = $this->actingAs($sender)
            ->postJson("/api/v1/streams/{$stream->id}/gift", [
                'gift_id' => $gift->id,
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'This stream is not currently live.']);
    }

    public function test_cannot_gift_yourself(): void
    {
        $streamer = User::factory()->create(['role' => 'creator']);
        Wallet::factory()->create(['user_id' => $streamer->id, 'balance' => 100]);

        $gift = Gift::factory()->create(['coin_price' => 10]);

        $stream = LiveStream::factory()->create([
            'user_id' => $streamer->id,
            'is_live' => true,
        ]);
        $stream->goLive();

        $response = $this->actingAs($streamer)
            ->postJson("/api/v1/streams/{$stream->id}/gift", [
                'gift_id' => $gift->id,
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'You cannot send gifts to yourself.']);
    }

    public function test_cannot_gift_with_insufficient_balance(): void
    {
        $sender = User::factory()->create();
        $streamer = User::factory()->create(['role' => 'creator']);

        Wallet::factory()->create(['user_id' => $sender->id, 'balance' => 5]);
        Wallet::factory()->create(['user_id' => $streamer->id, 'balance' => 0]);

        $gift = Gift::factory()->create(['coin_price' => 10]);

        $stream = LiveStream::factory()->create([
            'user_id' => $streamer->id,
            'is_live' => true,
        ]);
        $stream->goLive();

        $response = $this->actingAs($sender)
            ->postJson("/api/v1/streams/{$stream->id}/gift", [
                'gift_id' => $gift->id,
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Insufficient balance.']);
    }

    public function test_gift_requires_valid_gift_id(): void
    {
        $sender = User::factory()->create();
        $streamer = User::factory()->create(['role' => 'creator']);

        $stream = LiveStream::factory()->create([
            'user_id' => $streamer->id,
            'is_live' => true,
        ]);
        $stream->goLive();

        $response = $this->actingAs($sender)
            ->postJson("/api/v1/streams/{$stream->id}/gift", [
                'gift_id' => 99999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['gift_id']);
    }

    public function test_gift_requires_authentication(): void
    {
        $streamer = User::factory()->create(['role' => 'creator']);
        $gift = Gift::factory()->create();

        $stream = LiveStream::factory()->create([
            'user_id' => $streamer->id,
            'is_live' => true,
        ]);

        $response = $this->postJson("/api/v1/streams/{$stream->id}/gift", [
            'gift_id' => $gift->id,
        ]);

        $response->assertStatus(401);
    }
}
