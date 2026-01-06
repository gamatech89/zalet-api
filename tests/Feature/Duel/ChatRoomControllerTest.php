<?php

declare(strict_types=1);

use App\Domains\Duel\Enums\ChatRoomType;
use App\Domains\Duel\Events\MessageSent;
use App\Domains\Duel\Models\ChatRoom;
use App\Domains\Duel\Models\Message;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Event;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('ChatRoom Controller', function (): void {
    describe('GET /api/v1/chat-rooms', function (): void {
        it('lists active chat rooms', function (): void {
            $user = User::factory()->create();
            ChatRoom::factory()->kafana()->count(3)->create();
            ChatRoom::factory()->inactive()->count(2)->create();

            $response = $this->actingAs($user)->getJson('/api/v1/chat-rooms');

            $response->assertOk()
                ->assertJsonStructure([
                    'data' => [
                        '*' => ['id', 'uuid', 'name', 'type', 'isActive'],
                    ],
                ]);

            expect($response->json('data'))->toHaveCount(3);
        });

        it('filters by type', function (): void {
            $user = User::factory()->create();
            ChatRoom::factory()->kafana()->count(2)->create();
            ChatRoom::factory()->duel()->count(3)->create();

            $response = $this->actingAs($user)->getJson('/api/v1/chat-rooms?type=duel');

            $response->assertOk();
            expect($response->json('data'))->toHaveCount(3);

            foreach ($response->json('data') as $room) {
                expect($room['type'])->toBe('duel');
            }
        });

        it('filters by location_id', function (): void {
            $user = User::factory()->create();
            $location = \App\Domains\Identity\Models\Location::factory()->create();
            ChatRoom::factory()->forLocation($location)->count(2)->create();
            ChatRoom::factory()->count(3)->create();

            $response = $this->actingAs($user)->getJson("/api/v1/chat-rooms?location_id={$location->id}");

            $response->assertOk();
            expect($response->json('data'))->toHaveCount(2);
        });
    });

    describe('POST /api/v1/chat-rooms', function (): void {
        it('creates a new chat room', function (): void {
            $user = User::factory()->create(['role' => \App\Domains\Shared\Enums\UserRole::Creator]);

            $response = $this->actingAs($user)->postJson('/api/v1/chat-rooms', [
                'name' => 'My Cool Kafana',
            ]);

            $response->assertCreated()
                ->assertJsonPath('data.name', 'My Cool Kafana')
                ->assertJsonPath('data.type', 'public_kafana')
                ->assertJsonPath('data.isActive', true);
        });

        it('does not allow private room type via API', function (): void {
            $user = User::factory()->create(['role' => \App\Domains\Shared\Enums\UserRole::Creator]);

            $response = $this->actingAs($user)->postJson('/api/v1/chat-rooms', [
                'name' => 'Private Room',
                'type' => 'private',
            ]);

            // Private rooms are created as public_kafana since API only supports public room creation
            // Direct messages use the /conversations endpoint instead
            $response->assertCreated()
                ->assertJsonPath('data.type', 'public_kafana');
        });

        it('creates room with location', function (): void {
            $user = User::factory()->create(['role' => \App\Domains\Shared\Enums\UserRole::Creator]);
            $location = \App\Domains\Identity\Models\Location::factory()->create();

            $response = $this->actingAs($user)->postJson('/api/v1/chat-rooms', [
                'name' => 'Belgrade Kafana',
                'location_id' => $location->id,
            ]);

            $response->assertCreated()
                ->assertJsonPath('data.location.id', $location->id);
        });

        it('validates name length', function (): void {
            $user = User::factory()->create(['role' => \App\Domains\Shared\Enums\UserRole::Creator]);

            $response = $this->actingAs($user)->postJson('/api/v1/chat-rooms', [
                'name' => 'AB', // too short
            ]);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['name']);
        });
    });

    describe('GET /api/v1/chat-rooms/{uuid}', function (): void {
        it('returns a specific chat room', function (): void {
            $user = User::factory()->create();
            $room = ChatRoom::factory()->create(['name' => 'Test Room']);

            $response = $this->actingAs($user)->getJson("/api/v1/chat-rooms/{$room->uuid}");

            $response->assertOk()
                ->assertJsonPath('data.uuid', $room->uuid)
                ->assertJsonPath('data.name', 'Test Room');
        });

        it('returns 404 for non-existent room', function (): void {
            $user = User::factory()->create();
            $fakeUuid = '00000000-0000-0000-0000-000000000000';

            $response = $this->actingAs($user)->getJson("/api/v1/chat-rooms/{$fakeUuid}");

            $response->assertNotFound();
        });
    });

    describe('GET /api/v1/chat-rooms/{uuid}/messages', function (): void {
        it('returns messages in chat room', function (): void {
            $user = User::factory()->create();
            $room = ChatRoom::factory()->create();
            Message::factory()->inRoom($room)->count(10)->create();

            $response = $this->actingAs($user)->getJson("/api/v1/chat-rooms/{$room->uuid}/messages");

            $response->assertOk()
                ->assertJsonStructure([
                    'data' => [
                        '*' => ['id', 'uuid', 'type', 'content', 'createdAt'],
                    ],
                ]);

            expect($response->json('data'))->toHaveCount(10);
        });

        it('paginates messages', function (): void {
            $user = User::factory()->create();
            $room = ChatRoom::factory()->create();
            Message::factory()->inRoom($room)->count(20)->create();

            $response = $this->actingAs($user)->getJson("/api/v1/chat-rooms/{$room->uuid}/messages?per_page=5");

            $response->assertOk();
            expect($response->json('data'))->toHaveCount(5);
            expect($response->json('meta.total'))->toBe(20);
        });

        it('returns messages in descending order', function (): void {
            $user = User::factory()->create();
            $room = ChatRoom::factory()->create();

            $olderMessage = Message::factory()->inRoom($room)->create(['created_at' => now()->subMinutes(5)]);
            $newerMessage = Message::factory()->inRoom($room)->create(['created_at' => now()]);

            $response = $this->actingAs($user)->getJson("/api/v1/chat-rooms/{$room->uuid}/messages");

            $messages = $response->json('data');
            // First message should be the newer one (descending order)
            expect($messages[0]['uuid'])->toBe($newerMessage->uuid);
            expect($messages[1]['uuid'])->toBe($olderMessage->uuid);
        });
    });

    describe('POST /api/v1/chat-rooms/{uuid}/messages', function (): void {
        it('sends a message to chat room', function (): void {
            Event::fake([MessageSent::class]);

            $user = User::factory()->create();
            $room = ChatRoom::factory()->create();

            $response = $this->actingAs($user)->postJson("/api/v1/chat-rooms/{$room->uuid}/messages", [
                'content' => 'Hello, world!',
            ]);

            $response->assertCreated()
                ->assertJsonPath('data.content', 'Hello, world!')
                ->assertJsonPath('data.type', 'text')
                ->assertJsonPath('data.user.id', $user->id);

            Event::assertDispatched(MessageSent::class);
        });

        it('sends message with metadata', function (): void {
            Event::fake([MessageSent::class]);

            $user = User::factory()->create();
            $room = ChatRoom::factory()->create();

            $response = $this->actingAs($user)->postJson("/api/v1/chat-rooms/{$room->uuid}/messages", [
                'content' => 'Check this out!',
                'meta' => ['reply_to' => 123],
            ]);

            $response->assertCreated();

            $message = Message::first();
            expect($message->meta['reply_to'])->toBe(123);
        });

        it('validates message content length', function (): void {
            $user = User::factory()->create();
            $room = ChatRoom::factory()->create();

            $response = $this->actingAs($user)->postJson("/api/v1/chat-rooms/{$room->uuid}/messages", [
                'content' => str_repeat('a', 1001),
            ]);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['content']);
        });

        it('rejects message to inactive room', function (): void {
            $user = User::factory()->create();
            $room = ChatRoom::factory()->inactive()->create();

            $response = $this->actingAs($user)->postJson("/api/v1/chat-rooms/{$room->uuid}/messages", [
                'content' => 'Hello!',
            ]);

            $response->assertUnprocessable();
        });
    });

    describe('DELETE /api/v1/chat-rooms/{uuid}', function (): void {
        it('creator can deactivate room', function (): void {
            $user = User::factory()->create();
            $room = ChatRoom::factory()->create([
                'meta' => ['created_by' => $user->id],
            ]);

            $response = $this->actingAs($user)->deleteJson("/api/v1/chat-rooms/{$room->uuid}");

            $response->assertOk()
                ->assertJsonPath('message', 'Chat room deactivated.');

            $room->refresh();
            expect($room->is_active)->toBeFalse();
        });

        it('non-creator cannot deactivate room', function (): void {
            $creator = User::factory()->create();
            $otherUser = User::factory()->create();
            $room = ChatRoom::factory()->create([
                'meta' => ['created_by' => $creator->id],
            ]);

            $response = $this->actingAs($otherUser)->deleteJson("/api/v1/chat-rooms/{$room->uuid}");

            $response->assertForbidden();
        });
    });
});
