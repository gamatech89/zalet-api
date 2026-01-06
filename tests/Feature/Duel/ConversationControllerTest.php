<?php

declare(strict_types=1);

use App\Domains\Duel\Actions\StartConversationAction;
use App\Domains\Duel\Enums\ChatRoomType;
use App\Domains\Duel\Events\MessageSent;
use App\Domains\Duel\Models\ChatRoom;
use App\Domains\Duel\Models\Conversation;
use App\Domains\Duel\Models\Message;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Event;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('Conversation Controller', function (): void {
    describe('GET /api/v1/conversations', function (): void {
        it('lists user conversations', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();

            $action = app(StartConversationAction::class);
            $action->execute($user, $otherUser);

            $response = $this->actingAs($user)->getJson('/api/v1/conversations');

            $response->assertOk()
                ->assertJsonCount(1, 'data');
        });

        it('requires authentication', function (): void {
            $response = $this->getJson('/api/v1/conversations');

            $response->assertUnauthorized();
        });
    });

    describe('POST /api/v1/conversations', function (): void {
        it('starts a new conversation', function (): void {
            $user = User::factory()->create();
            $recipient = User::factory()->create();

            $response = $this->actingAs($user)->postJson('/api/v1/conversations', [
                'recipient_uuid' => $recipient->uuid,
            ]);

            $response->assertCreated()
                ->assertJsonPath('message', 'Conversation started.');

            expect(ChatRoom::where('type', ChatRoomType::DIRECT_MESSAGE)->count())->toBe(1);
        });

        it('returns existing conversation if one exists', function (): void {
            $user = User::factory()->create();
            $recipient = User::factory()->create();

            // Create first conversation
            $this->actingAs($user)->postJson('/api/v1/conversations', [
                'recipient_uuid' => $recipient->uuid,
            ]);

            // Try to create again
            $response = $this->actingAs($user)->postJson('/api/v1/conversations', [
                'recipient_uuid' => $recipient->uuid,
            ]);

            $response->assertCreated();
            expect(ChatRoom::where('type', ChatRoomType::DIRECT_MESSAGE)->count())->toBe(1);
        });

        it('prevents messaging yourself', function (): void {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->postJson('/api/v1/conversations', [
                'recipient_uuid' => $user->uuid,
            ]);

            $response->assertStatus(422)
                ->assertJsonPath('message', 'Cannot start a conversation with yourself.');
        });

        it('validates recipient exists', function (): void {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->postJson('/api/v1/conversations', [
                'recipient_uuid' => '00000000-0000-0000-0000-000000000000',
            ]);

            $response->assertStatus(422);
        });
    });

    describe('GET /api/v1/conversations/{uuid}', function (): void {
        it('returns conversation details', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();

            $action = app(StartConversationAction::class);
            $room = $action->execute($user, $otherUser);

            $response = $this->actingAs($user)->getJson("/api/v1/conversations/{$room->uuid}");

            $response->assertOk()
                ->assertJsonPath('data.roomUuid', $room->uuid);
        });

        it('denies access to non-participants', function (): void {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();
            $user3 = User::factory()->create();

            $action = app(StartConversationAction::class);
            $room = $action->execute($user1, $user2);

            $response = $this->actingAs($user3)->getJson("/api/v1/conversations/{$room->uuid}");

            $response->assertForbidden();
        });
    });

    describe('GET /api/v1/conversations/{uuid}/messages', function (): void {
        it('returns messages in conversation', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();

            $action = app(StartConversationAction::class);
            $room = $action->execute($user, $otherUser);

            Message::factory()->inRoom($room)->fromUser($otherUser)->count(5)->create();

            $response = $this->actingAs($user)->getJson("/api/v1/conversations/{$room->uuid}/messages");

            $response->assertOk()
                ->assertJsonCount(5, 'data');
        });

        it('marks conversation as read when fetching messages', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();

            $action = app(StartConversationAction::class);
            $room = $action->execute($user, $otherUser);

            Message::factory()->inRoom($room)->fromUser($otherUser)->create();

            $conversation = $room->conversations()->where('user_id', $user->id)->first();
            expect($conversation->last_read_at)->toBeNull();

            $this->actingAs($user)->getJson("/api/v1/conversations/{$room->uuid}/messages");

            $conversation->refresh();
            expect($conversation->last_read_at)->not->toBeNull();
        });
    });

    describe('POST /api/v1/conversations/{uuid}/messages', function (): void {
        it('sends a message in conversation', function (): void {
            Event::fake([MessageSent::class]);

            $user = User::factory()->create();
            $otherUser = User::factory()->create();

            $action = app(StartConversationAction::class);
            $room = $action->execute($user, $otherUser);

            $response = $this->actingAs($user)->postJson("/api/v1/conversations/{$room->uuid}/messages", [
                'content' => 'Hello!',
            ]);

            $response->assertCreated()
                ->assertJsonPath('data.content', 'Hello!');

            Event::assertDispatched(MessageSent::class);
        });

        it('denies sending if conversation is blocked by sender', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();

            $action = app(StartConversationAction::class);
            $room = $action->execute($user, $otherUser);

            // Block the conversation
            $conversation = $room->conversations()->where('user_id', $user->id)->first();
            $conversation->setBlocked(true);

            $response = $this->actingAs($user)->postJson("/api/v1/conversations/{$room->uuid}/messages", [
                'content' => 'Hello!',
            ]);

            $response->assertForbidden()
                ->assertJsonPath('message', 'This conversation is blocked.');
        });

        it('denies sending if blocked by recipient', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();

            $action = app(StartConversationAction::class);
            $room = $action->execute($user, $otherUser);

            // Other user blocks
            $otherConversation = $room->conversations()->where('user_id', $otherUser->id)->first();
            $otherConversation->setBlocked(true);

            $response = $this->actingAs($user)->postJson("/api/v1/conversations/{$room->uuid}/messages", [
                'content' => 'Hello!',
            ]);

            $response->assertForbidden()
                ->assertJsonPath('message', 'Cannot send message to this user.');
        });
    });

    describe('POST /api/v1/conversations/{uuid}/read', function (): void {
        it('marks conversation as read', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();

            $action = app(StartConversationAction::class);
            $room = $action->execute($user, $otherUser);

            $response = $this->actingAs($user)->postJson("/api/v1/conversations/{$room->uuid}/read");

            $response->assertOk()
                ->assertJsonPath('message', 'Marked as read.');
        });
    });

    describe('POST /api/v1/conversations/{uuid}/mute', function (): void {
        it('toggles mute status', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();

            $action = app(StartConversationAction::class);
            $room = $action->execute($user, $otherUser);

            // Mute
            $response = $this->actingAs($user)->postJson("/api/v1/conversations/{$room->uuid}/mute");
            $response->assertOk()
                ->assertJsonPath('is_muted', true);

            // Unmute
            $response = $this->actingAs($user)->postJson("/api/v1/conversations/{$room->uuid}/mute");
            $response->assertOk()
                ->assertJsonPath('is_muted', false);
        });
    });

    describe('POST /api/v1/conversations/{uuid}/block', function (): void {
        it('blocks a conversation', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();

            $action = app(StartConversationAction::class);
            $room = $action->execute($user, $otherUser);

            $response = $this->actingAs($user)->postJson("/api/v1/conversations/{$room->uuid}/block", [
                'blocked' => true,
            ]);

            $response->assertOk()
                ->assertJsonPath('is_blocked', true)
                ->assertJsonPath('message', 'Conversation blocked.');
        });

        it('unblocks a conversation', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();

            $action = app(StartConversationAction::class);
            $room = $action->execute($user, $otherUser);

            // Block first
            $room->conversations()->where('user_id', $user->id)->first()->setBlocked(true);

            $response = $this->actingAs($user)->postJson("/api/v1/conversations/{$room->uuid}/block", [
                'blocked' => false,
            ]);

            $response->assertOk()
                ->assertJsonPath('is_blocked', false)
                ->assertJsonPath('message', 'Conversation unblocked.');
        });
    });
});
