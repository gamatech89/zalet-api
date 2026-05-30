<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CinemaTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->creator()->create();
    }

    /**
     * Test cinema feed is publicly accessible.
     */
    public function test_cinema_feed_is_publicly_accessible(): void
    {
        Media::factory()->count(3)->create([
            'type' => 'embed',
            'provider' => 'youtube',
        ]);

        $response = $this->getJson('/api/v1/cinema');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    /**
     * Test create embed from YouTube URL.
     */
    public function test_create_embed_from_youtube_url(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/cinema', [
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'title' => 'Never Gonna Give You Up',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.provider', 'youtube')
            ->assertJsonPath('data.title', 'Never Gonna Give You Up');

        $this->assertDatabaseHas('media', [
            'type' => 'embed',
            'provider' => 'youtube',
        ]);
    }

    /**
     * Test create embed from short YouTube URL.
     */
    public function test_create_embed_from_short_youtube_url(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/cinema', [
                'url' => 'https://youtu.be/dQw4w9WgXcQ',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.provider', 'youtube');
    }

    /**
     * Test create embed from Vimeo URL.
     */
    public function test_create_embed_from_vimeo_url(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/cinema', [
                'url' => 'https://vimeo.com/123456789',
                'title' => 'Vimeo Video',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.provider', 'vimeo');
    }

    /**
     * Test create embed from Dailymotion URL.
     */
    public function test_create_embed_from_dailymotion_url(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/cinema', [
                'url' => 'https://www.dailymotion.com/video/x8abc123',
                'title' => 'Dailymotion Video',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.provider', 'dailymotion');
    }

    /**
     * Test reject invalid embed URL.
     */
    public function test_reject_invalid_embed_url(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/cinema', [
                'url' => 'https://example.com/video.mp4',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    }

    /**
     * Test create PPV embed.
     */
    public function test_create_ppv_embed(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/cinema', [
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'title' => 'Premium Video',
                'is_ppv' => true,
                'price_coins' => 50,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_ppv', true)
            ->assertJsonPath('data.price_coins', '50.00');
    }

    /**
     * Test PPV embed requires purchase.
     */
    public function test_ppv_embed_requires_purchase(): void
    {
        $embed = Media::factory()->create([
            'type' => 'embed',
            'provider' => 'youtube',
            'is_ppv' => true,
            'price_coins' => 50,
        ]);

        $response = $this->getJson("/api/v1/cinema/{$embed->id}");

        $response->assertStatus(403)
            ->assertJsonPath('is_ppv', true);
    }

    /**
     * Test cinema requires authentication to create.
     */
    public function test_create_cinema_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/cinema', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response->assertStatus(401);
    }
}
