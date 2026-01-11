<?php

declare(strict_types=1);

use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('Auth Registration', function (): void {
    beforeEach(function (): void {
        // Seed a location for testing
        Location::create([
            'city' => 'Beograd',
            'country' => 'Srbija',
            'country_code' => 'RS',
            'latitude' => 44.8176,
            'longitude' => 20.4633,
        ]);
    });

    it('can register a new user', function (): void {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'username' => 'testuser',
            'display_name' => 'Test User',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id',
                        'role',
                        'profile' => [
                            'username',
                            'displayName',
                        ],
                        'wallet' => [
                            'balance',
                            'currency',
                        ],
                    ],
                    'token',
                ],
                'message',
            ])
            ->assertJsonPath('data.user.role', 'user');

        // Verify user was created in database with email
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
        
        // Verify profile was created with username
        $this->assertDatabaseHas('profiles', [
            'username' => 'testuser',
        ]);

        expect(User::where('email', 'test@example.com')->exists())->toBeTrue();
    });

    it('can register with origin location', function (): void {
        $location = Location::first();

        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'username' => 'userwithlocation',
            'origin_location_id' => $location->id,
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'user@example.com')->first();
        expect($user->profile->origin_location_id)->toBe($location->id);
    });

    it('validates required fields', function (): void {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password', 'username']);
    });

    it('validates unique email', function (): void {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'username' => 'newuser',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('validates unique username', function (): void {
        $existingUser = User::factory()->create();
        $existingUser->profile()->create([
            'username' => 'takenusername',
            'display_name' => 'Existing User',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'username' => 'takenusername',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    });

    it('validates password confirmation', function (): void {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'differentpassword',
            'username' => 'testuser',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    });

    it('validates username format', function (): void {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'username' => 'invalid username!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    });
});

describe('Auth Login', function (): void {
    it('can login with valid credentials', function (): void {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => bcrypt('password123'),
        ]);
        $user->profile()->create([
            'username' => 'loginuser',
            'display_name' => 'Login User',
        ]);
        $user->wallet()->create([
            'balance' => 0,
            'currency' => 'CREDITS',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'role'],
                    'token',
                ],
                'message',
            ])
            ->assertJsonPath('data.message', null); // No data.message, only root message
    });

    it('fails with invalid credentials', function (): void {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('fails with non-existent email', function (): void {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });
});

describe('Auth Logout', function (): void {
    it('can logout authenticated user', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJson(['message' => 'Logged out successfully']);
    });

    it('requires authentication', function (): void {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    });
});

describe('Auth Me', function (): void {
    it('returns current user data', function (): void {
        $user = User::factory()->create();
        $user->profile()->create([
            'username' => 'currentuser',
            'display_name' => 'Current User',
        ]);
        $user->wallet()->create([
            'balance' => 100,
            'currency' => 'CREDITS',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'email',
                    'role',
                    'profile' => [
                        'username',
                        'displayName',
                    ],
                    'wallet' => [
                        'balance',
                        'currency',
                    ],
                ],
            ]);
    });

    it('requires authentication', function (): void {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    });
});
