<?php

declare(strict_types=1);

use App\Domains\Duel\Enums\ChatRoomType;
use App\Domains\Duel\Models\ChatRoom;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Enums\UserRole;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('ChatRoom Permission Tests', function (): void {
    describe('POST /api/v1/chat-rooms (permissions)', function (): void {
        it('admin can create public room', function (): void {
            $admin = User::factory()->create(['role' => UserRole::Admin]);

            $response = $this->actingAs($admin)->postJson('/api/v1/chat-rooms', [
                'name' => 'Admin Kafana',
                'type' => 'public_kafana',
            ]);

            $response->assertCreated()
                ->assertJsonPath('data.type', 'public_kafana')
                ->assertJsonPath('data.name', 'Admin Kafana');
        });

        it('moderator can create public room', function (): void {
            $moderator = User::factory()->create(['role' => UserRole::Moderator]);

            $response = $this->actingAs($moderator)->postJson('/api/v1/chat-rooms', [
                'name' => 'Mod Kafana',
                'type' => 'public_kafana',
            ]);

            $response->assertCreated();
        });

        it('creator can create public room', function (): void {
            $creator = User::factory()->create(['role' => UserRole::Creator]);

            $response = $this->actingAs($creator)->postJson('/api/v1/chat-rooms', [
                'name' => 'Creator Kafana',
                'type' => 'public_kafana',
            ]);

            $response->assertCreated();
        });

        it('regular user cannot create public room', function (): void {
            $user = User::factory()->create(['role' => UserRole::User]);

            $response = $this->actingAs($user)->postJson('/api/v1/chat-rooms', [
                'name' => 'User Kafana',
                'type' => 'public_kafana',
            ]);

            $response->assertForbidden()
                ->assertJsonPath('message', 'You do not have permission to create public chat rooms. Only creators, moderators, and admins can create public rooms.');
        });

        it('includes creator info in response', function (): void {
            $admin = User::factory()->create(['role' => UserRole::Admin]);

            $response = $this->actingAs($admin)->postJson('/api/v1/chat-rooms', [
                'name' => 'Admin Kafana',
            ]);

            $response->assertCreated()
                ->assertJsonPath('data.creator.uuid', $admin->uuid);
        });

        it('creates room with description', function (): void {
            $admin = User::factory()->create(['role' => UserRole::Admin]);

            $response = $this->actingAs($admin)->postJson('/api/v1/chat-rooms', [
                'name' => 'Test Kafana',
                'description' => 'A test kafana description',
            ]);

            $response->assertCreated()
                ->assertJsonPath('data.description', 'A test kafana description');
        });

        it('creates room with location', function (): void {
            $admin = User::factory()->create(['role' => UserRole::Admin]);
            $location = Location::factory()->create(['city' => 'Test City']);

            $response = $this->actingAs($admin)->postJson('/api/v1/chat-rooms', [
                'name' => 'City Kafana',
                'location_id' => $location->id,
            ]);

            $response->assertCreated()
                ->assertJsonPath('data.location.city', 'Test City');
        });
    });

    describe('GET /api/v1/chat-rooms (listing)', function (): void {
        it('only lists public kafanas by default', function (): void {
            $user = User::factory()->create();

            ChatRoom::factory()->count(3)->create(['type' => ChatRoomType::PUBLIC_KAFANA]);
            ChatRoom::factory()->count(2)->create(['type' => ChatRoomType::DIRECT_MESSAGE]);
            ChatRoom::factory()->count(1)->create(['type' => ChatRoomType::DUEL]);

            $response = $this->actingAs($user)->getJson('/api/v1/chat-rooms');

            $response->assertOk()
                ->assertJsonCount(3, 'data');
        });

        it('includes creator info in listing', function (): void {
            $creator = User::factory()->create(['role' => UserRole::Creator]);
            ChatRoom::factory()->create([
                'type' => ChatRoomType::PUBLIC_KAFANA,
                'creator_id' => $creator->id,
            ]);

            $response = $this->actingAs($creator)->getJson('/api/v1/chat-rooms');

            $response->assertOk()
                ->assertJsonPath('data.0.creator.uuid', $creator->uuid);
        });

        it('filters by location', function (): void {
            $user = User::factory()->create();
            $location = Location::factory()->create();

            ChatRoom::factory()->count(2)->create([
                'type' => ChatRoomType::PUBLIC_KAFANA,
                'location_id' => $location->id,
            ]);
            ChatRoom::factory()->create(['type' => ChatRoomType::PUBLIC_KAFANA]);

            $response = $this->actingAs($user)->getJson("/api/v1/chat-rooms?location_id={$location->id}");

            $response->assertOk()
                ->assertJsonCount(2, 'data');
        });
    });
});
