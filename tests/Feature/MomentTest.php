<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Services\CoinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MomentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected CoinService $coinService;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        $this->user = User::factory()->creator()->create([
            'storage_limit_mb' => 512,
            'storage_used_bytes' => 0,
        ]);
        $this->coinService = app(CoinService::class);
    }

    /**
     * Test moments feed is publicly accessible.
     */
    public function test_moments_feed_is_publicly_accessible(): void
    {
        Media::factory()->count(3)->create([
            'type' => 'moment',
            'provider' => 'native',
        ]);

        $response = $this->getJson('/api/v1/moments');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    /**
     * Test single moment is accessible.
     */
    public function test_single_moment_is_accessible(): void
    {
        $moment = Media::factory()->create([
            'type' => 'moment',
            'provider' => 'native',
            'is_ppv' => false,
        ]);

        $response = $this->getJson("/api/v1/moments/{$moment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $moment->id);
    }

    /**
     * Test PPV moment requires purchase.
     */
    public function test_ppv_moment_requires_purchase(): void
    {
        $moment = Media::factory()->create([
            'type' => 'moment',
            'provider' => 'native',
            'is_ppv' => true,
            'price_coins' => 50,
        ]);

        $response = $this->getJson("/api/v1/moments/{$moment->id}");

        $response->assertStatus(403)
            ->assertJsonPath('is_ppv', true);
    }

    /**
     * Test upload moment requires authentication.
     */
    public function test_upload_moment_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/moments', [
            'title' => 'Test Moment',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test upload moment successfully.
     */
    public function test_upload_moment_successfully(): void
    {
        $video = UploadedFile::fake()->create('video.mp4', 1024 * 5); // 5MB

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/moments', [
                'video' => $video,
                'title' => 'My First Moment',
                'description' => 'This is a test moment',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'My First Moment');

        $this->assertDatabaseHas('media', [
            'user_id' => $this->user->id,
            'type' => 'moment',
            'title' => 'My First Moment',
        ]);
    }

    /**
     * Test upload PPV moment.
     */
    public function test_upload_ppv_moment(): void
    {
        $video = UploadedFile::fake()->create('video.mp4', 1024 * 5);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/moments', [
                'video' => $video,
                'title' => 'Premium Content',
                'is_ppv' => true,
                'price_coins' => 100,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_ppv', true)
            ->assertJsonPath('data.price_coins', '100.00');
    }

    /**
     * Test delete moment requires ownership.
     */
    public function test_delete_moment_requires_ownership(): void
    {
        $owner = User::factory()->create();
        $moment = Media::factory()->create([
            'user_id' => $owner->id,
            'type' => 'moment',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/moments/{$moment->id}");

        $response->assertStatus(403);
    }

    /**
     * Test owner can delete moment.
     */
    public function test_owner_can_delete_moment(): void
    {
        $moment = Media::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'moment',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/moments/{$moment->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('media', ['id' => $moment->id]);
    }

    /**
     * Test storage quota is checked.
     */
    public function test_storage_quota_is_checked(): void
    {
        // Set user to almost full storage (511MB used of 512MB limit)
        $this->user->update(['storage_used_bytes' => 511 * 1024 * 1024]);

        $video = UploadedFile::fake()->create('video.mp4', 1024 * 10); // 10MB - exceeds remaining

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/moments', [
                'video' => $video,
                'title' => 'Too Large',
            ]);

        $response->assertStatus(422);
    }
}
