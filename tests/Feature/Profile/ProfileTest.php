<?php

declare(strict_types=1);

use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('Profile Show', function (): void {
    it('returns authenticated user profile', function (): void {
        $location = Location::create([
            'city' => 'Beograd',
            'country' => 'Srbija',
            'country_code' => 'RS',
        ]);

        $user = User::factory()->create();
        $user->profile()->create([
            'username' => 'testuser',
            'display_name' => 'Test User',
            'bio' => 'Hello world',
            'origin_location_id' => $location->id,
            'is_private' => false,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/profile');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'username',
                    'displayName',
                    'bio',
                    'isPrivate',
                    'originLocation' => [
                        'id',
                        'city',
                        'country',
                        'countryCode',
                    ],
                ],
            ]);
    });

    it('requires authentication', function (): void {
        $response = $this->getJson('/api/v1/profile');

        $response->assertStatus(401);
    });
});

describe('Profile Update', function (): void {
    it('can update profile fields', function (): void {
        $user = User::factory()->create();
        $user->profile()->create([
            'username' => 'oldusername',
            'display_name' => 'Old Name',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/profile', [
                'username' => 'newusername',
                'display_name' => 'New Name',
                'bio' => 'Updated bio',
            ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'username' => 'newusername',
                    'displayName' => 'New Name',
                    'bio' => 'Updated bio',
                ],
                'message' => 'Profile updated successfully',
            ]);

        expect($user->fresh()->profile->username)->toBe('newusername');
    });

    it('can update origin location', function (): void {
        $location = Location::create([
            'city' => 'Novi Sad',
            'country' => 'Srbija',
            'country_code' => 'RS',
        ]);

        $user = User::factory()->create();
        $user->profile()->create([
            'username' => 'testuser',
            'display_name' => 'Test User',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/profile', [
                'origin_location_id' => $location->id,
            ]);

        $response->assertOk();
        expect($user->fresh()->profile->origin_location_id)->toBe($location->id);
    });

    it('validates unique username on update', function (): void {
        $existingUser = User::factory()->create();
        $existingUser->profile()->create(['username' => 'takenname', 'display_name' => 'Existing']);

        $user = User::factory()->create();
        $user->profile()->create(['username' => 'myname', 'display_name' => 'Test']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/profile', [
                'username' => 'takenname',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    });

    it('allows keeping own username', function (): void {
        $user = User::factory()->create();
        $user->profile()->create(['username' => 'myusername', 'display_name' => 'Test']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/profile', [
                'username' => 'myusername',
                'bio' => 'Updated bio only',
            ]);

        $response->assertOk();
    });

    it('requires authentication', function (): void {
        $response = $this->putJson('/api/v1/profile', [
            'username' => 'newusername',
        ]);

        $response->assertStatus(401);
    });
});
