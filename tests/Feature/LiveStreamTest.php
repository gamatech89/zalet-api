<?php

namespace Tests\Feature;

use App\Models\LiveStream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveStreamTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_can_create_stream(): void
    {
        $creator = User::factory()->create(['role' => 'creator']);

        $response = $this->actingAs($creator)
            ->postJson('/api/v1/streams', ['title' => 'My First Stream']);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'title', 'stream_key', 'stream_mode', 'is_live', 'livekit_token', 'livekit_ws_url', 'created_at'],
            ]);

        $this->assertDatabaseHas('live_streams', [
            'user_id' => $creator->id,
            'title' => 'My First Stream',
        ]);
    }

    public function test_regular_user_cannot_create_stream(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/streams', ['title' => 'My Stream']);

        $response->assertStatus(403);
    }

    public function test_creator_can_get_stream_key(): void
    {
        $creator = User::factory()->create(['role' => 'creator']);
        $stream = LiveStream::factory()->create(['user_id' => $creator->id]);

        $response = $this->actingAs($creator)
            ->getJson('/api/v1/streams/key');

        $response->assertStatus(200)
            ->assertJsonPath('data.stream_key', $stream->stream_key);
    }

    public function test_creator_can_start_stream(): void
    {
        $creator = User::factory()->create(['role' => 'creator']);
        LiveStream::factory()->create([
            'user_id' => $creator->id,
            'is_live' => false,
        ]);

        $response = $this->actingAs($creator)
            ->postJson('/api/v1/streams/start');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => ['stream_id', 'session_id', 'started_at'],
            ]);

        $this->assertDatabaseHas('live_streams', [
            'user_id' => $creator->id,
            'is_live' => true,
        ]);
    }

    public function test_creator_can_stop_stream(): void
    {
        $creator = User::factory()->create(['role' => 'creator']);
        $stream = LiveStream::factory()->create([
            'user_id' => $creator->id,
            'is_live' => true,
        ]);
        $stream->goLive();

        $response = $this->actingAs($creator)
            ->postJson('/api/v1/streams/stop');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => ['stream_id', 'duration_minutes', 'total_coins_collected'],
            ]);

        $this->assertDatabaseHas('live_streams', [
            'id' => $stream->id,
            'is_live' => false,
        ]);
    }

    public function test_cannot_start_already_live_stream(): void
    {
        $creator = User::factory()->create(['role' => 'creator']);
        $stream = LiveStream::factory()->create([
            'user_id' => $creator->id,
            'is_live' => true,
        ]);
        $stream->goLive();

        $response = $this->actingAs($creator)
            ->postJson('/api/v1/streams/start');

        $response->assertStatus(409)
            ->assertJson(['message' => 'Stream is already live.']);
    }

    public function test_can_list_live_streams(): void
    {
        $creator1 = User::factory()->create(['role' => 'creator']);
        $creator2 = User::factory()->create(['role' => 'creator']);

        $stream1 = LiveStream::factory()->create([
            'user_id' => $creator1->id,
            'is_live' => true,
        ]);
        $stream1->goLive();

        $stream2 = LiveStream::factory()->create([
            'user_id' => $creator2->id,
            'is_live' => true,
        ]);
        $stream2->goLive();

        // Offline stream should not appear
        LiveStream::factory()->create([
            'user_id' => $creator1->id,
            'is_live' => false,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/streams/live');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'streamer' => ['id', 'username']],
                ],
                'meta',
            ]);
    }

    public function test_stream_requires_title(): void
    {
        $creator = User::factory()->create(['role' => 'creator']);

        $response = $this->actingAs($creator)
            ->postJson('/api/v1/streams', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_cannot_create_stream_while_live(): void
    {
        $creator = User::factory()->create(['role' => 'creator']);
        $existingStream = LiveStream::factory()->create([
            'user_id' => $creator->id,
            'is_live' => true,
        ]);

        $response = $this->actingAs($creator)
            ->postJson('/api/v1/streams', ['title' => 'New Stream']);

        $response->assertStatus(409)
            ->assertJson(['stream_id' => $existingStream->id]);
    }
}
