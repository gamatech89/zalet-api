<?php

declare(strict_types=1);

use App\Domains\Identity\Models\Follow;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\Profile;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Get User by UUID', function (): void {
    test('can get public user profile by uuid', function (): void {
        $user = User::factory()->create();
        Profile::factory()->create(['user_id' => $user->id]);

        $response = $this->getJson("/api/v1/users/{$user->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'email',
                    'role',
                    'profile',
                    'createdAt',
                ],
            ]);
    });

    test('user data includes profile', function (): void {
        $user = User::factory()->create();
        Profile::factory()->create(['user_id' => $user->id]);

        $response = $this->getJson("/api/v1/users/{$user->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'profile' => [
                        'username',
                        'displayName',
                        'bio',
                        'isPrivate',
                    ],
                ],
            ]);
    });

    test('returns 404 for non-existent uuid', function (): void {
        $fakeUuid = \Illuminate\Support\Str::uuid()->toString();

        $response = $this->getJson("/api/v1/users/{$fakeUuid}");

        $response->assertStatus(404);
    });

    test('private profiles are still accessible', function (): void {
        $user = User::factory()->create();
        Profile::factory()->create(['user_id' => $user->id, 'is_private' => true]);

        $response = $this->getJson("/api/v1/users/{$user->uuid}");

        // Still accessible but privacy flag is visible
        $response->assertStatus(200)
            ->assertJsonPath('data.profile.isPrivate', true);
    });
});

describe('Get Users by Location', function (): void {
    beforeEach(function (): void {
        $this->location = Location::create([
            'city' => 'Belgrade',
            'country' => 'Serbia',
            'country_code' => 'RS',
        ]);

        // Create users with this location as origin
        $this->originUsers = [];
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create();
            Profile::factory()->create([
                'user_id' => $user->id,
                'origin_location_id' => $this->location->id,
            ]);
            $this->originUsers[] = $user;
        }

        // Create users with this location as current
        $this->currentUsers = [];
        for ($i = 0; $i < 2; $i++) {
            $user = User::factory()->create();
            Profile::factory()->create([
                'user_id' => $user->id,
                'current_location_id' => $this->location->id,
            ]);
            $this->currentUsers[] = $user;
        }

        // Create private user with this location (should be excluded)
        $this->privateUser = User::factory()->create();
        Profile::factory()->create([
            'user_id' => $this->privateUser->id,
            'origin_location_id' => $this->location->id,
            'is_private' => true,
        ]);
    });

    test('can get users by location (origin)', function (): void {
        $response = $this->getJson("/api/v1/locations/{$this->location->id}/users?type=origin");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    });

    test('can get users by location (current)', function (): void {
        $response = $this->getJson("/api/v1/locations/{$this->location->id}/users?type=current");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    });

    test('default type returns origin users', function (): void {
        $response = $this->getJson("/api/v1/locations/{$this->location->id}/users");

        $response->assertStatus(200);
        // Default is origin type
        $response->assertJsonCount(3, 'data');
    });

    test('private profiles are excluded from location search', function (): void {
        $response = $this->getJson("/api/v1/locations/{$this->location->id}/users?type=origin");

        $response->assertStatus(200);
        $uuids = collect($response->json('data'))->pluck('id');
        expect($uuids)->not->toContain($this->privateUser->uuid);
    });

    test('location users list is paginated', function (): void {
        $response = $this->getJson("/api/v1/locations/{$this->location->id}/users?per_page=2&type=origin");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'currentPage',
                    'lastPage',
                    'perPage',
                    'total',
                ],
            ]);
    });

    test('returns 404 for non-existent location', function (): void {
        $response = $this->getJson('/api/v1/locations/99999/users');

        $response->assertStatus(404);
    });
});

describe('User Counts', function (): void {
    test('user profile is accessible with followers', function (): void {
        $user = User::factory()->create();
        Profile::factory()->create(['user_id' => $user->id]);

        // Create followers
        for ($i = 0; $i < 5; $i++) {
            $follower = User::factory()->create();
            Profile::factory()->create(['user_id' => $follower->id]);
            Follow::create([
                'follower_id' => $follower->id,
                'following_id' => $user->id,
                'accepted_at' => now(),
            ]);
        }

        $response = $this->getJson("/api/v1/users/{$user->uuid}");

        $response->assertStatus(200);
    });

    test('user profile is accessible with following', function (): void {
        $user = User::factory()->create();
        Profile::factory()->create(['user_id' => $user->id]);

        // Create following
        for ($i = 0; $i < 3; $i++) {
            $followed = User::factory()->create();
            Profile::factory()->create(['user_id' => $followed->id]);
            Follow::create([
                'follower_id' => $user->id,
                'following_id' => $followed->id,
                'accepted_at' => now(),
            ]);
        }

        $response = $this->getJson("/api/v1/users/{$user->uuid}");

        $response->assertStatus(200);
    });
});
