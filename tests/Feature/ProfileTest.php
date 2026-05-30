<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================
    // Show Profile Tests
    // ==========================================

    public function test_authenticated_user_can_get_their_profile(): void
    {
        $user = User::factory()->create();
        $user->profile()->create([
            'bio' => 'Hello from Belgrade!',
            'hometown_city' => 'Belgrade',
            'hometown_country' => 'Serbia',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'profile' => ['id', 'user_id', 'bio', 'hometown_city', 'hometown_country'],
                'computed' => ['hometown', 'current_location'],
            ])
            ->assertJsonPath('profile.bio', 'Hello from Belgrade!')
            ->assertJsonPath('computed.hometown', 'Belgrade, Serbia');
    }

    public function test_profile_is_auto_created_if_missing(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'profile' => ['id', 'user_id'],
            ]);

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
        ]);
    }

    public function test_get_profile_fails_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/profile');

        $response->assertStatus(401);
    }

    // ==========================================
    // Update Profile Tests
    // ==========================================

    public function test_user_can_update_their_profile(): void
    {
        $user = User::factory()->create();
        $user->profile()->create([]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/profile', [
                'bio' => 'Updated bio',
                'hometown_city' => 'Sarajevo',
                'hometown_country' => 'Bosnia',
                'current_city' => 'Vienna',
                'current_country' => 'Austria',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('profile.bio', 'Updated bio')
            ->assertJsonPath('profile.hometown_city', 'Sarajevo')
            ->assertJsonPath('computed.hometown', 'Sarajevo, Bosnia')
            ->assertJsonPath('computed.current_location', 'Vienna, Austria');

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'bio' => 'Updated bio',
            'hometown_city' => 'Sarajevo',
        ]);
    }

    public function test_profile_update_with_coordinates(): void
    {
        $user = User::factory()->create();
        $user->profile()->create([]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/profile', [
                'coordinates' => [
                    'lat' => 44.7866,
                    'lng' => 20.4489,
                ],
            ]);

        $response->assertStatus(200);

        $profile = $user->profile()->first();
        $this->assertEquals(44.7866, $profile->coordinates['lat']);
        $this->assertEquals(20.4489, $profile->coordinates['lng']);
    }

    public function test_profile_update_fails_with_invalid_coordinates(): void
    {
        $user = User::factory()->create();
        $user->profile()->create([]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/profile', [
                'coordinates' => [
                    'lat' => 200, // Invalid: must be -90 to 90
                    'lng' => 20.4489,
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['coordinates.lat']);
    }

    public function test_profile_update_fails_with_long_bio(): void
    {
        $user = User::factory()->create();
        $user->profile()->create([]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/profile', [
                'bio' => str_repeat('a', 501), // Max is 500
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bio']);
    }

    // ==========================================
    // Avatar Upload Tests
    // ==========================================

    public function test_user_can_upload_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $user->profile()->create([]);
        $token = $user->createToken('test-token')->plainTextToken;

        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'avatar_url']);

        // Verify file was stored
        $profile = $user->profile()->first();
        $this->assertNotNull($profile->avatar_url);
        Storage::disk('public')->assertExists(
            str_replace('/storage/', '', $profile->avatar_url)
        );
    }

    public function test_avatar_upload_fails_with_invalid_file_type(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $user->profile()->create([]);
        $token = $user->createToken('test-token')->plainTextToken;

        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_avatar_upload_fails_with_large_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $user->profile()->create([]);
        $token = $user->createToken('test-token')->plainTextToken;

        // Create a file larger than 2MB
        $file = UploadedFile::fake()->image('large.jpg')->size(3000);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_avatar_upload_replaces_old_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $user->profile()->create([
            'avatar_url' => '/storage/avatars/old-avatar.jpg',
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        // Create old avatar file
        Storage::disk('public')->put('avatars/old-avatar.jpg', 'old content');

        $file = UploadedFile::fake()->image('new-avatar.jpg', 200, 200);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(200);

        // Old avatar should be deleted
        Storage::disk('public')->assertMissing('avatars/old-avatar.jpg');
    }
}
