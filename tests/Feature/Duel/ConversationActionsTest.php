<?php

declare(strict_types=1);

use App\Domains\Duel\Actions\CreatePublicRoomAction;
use App\Domains\Duel\Actions\GetUserConversationsAction;
use App\Domains\Duel\Actions\StartConversationAction;
use App\Domains\Duel\Enums\ChatRoomType;
use App\Domains\Duel\Models\ChatRoom;
use App\Domains\Duel\Models\Conversation;
use App\Domains\Duel\Models\Message;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Enums\UserRole;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('StartConversationAction', function (): void {
    it('creates a new DM conversation between two users', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $action = app(StartConversationAction::class);
        $room = $action->execute($user1, $user2);

        expect($room)->toBeInstanceOf(ChatRoom::class)
            ->and($room->type)->toBe(ChatRoomType::DIRECT_MESSAGE)
            ->and($room->creator_id)->toBe($user1->id)
            ->and($room->max_participants)->toBe(2)
            ->and($room->conversations)->toHaveCount(2);
    });

    it('returns existing conversation if one exists', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $action = app(StartConversationAction::class);
        $room1 = $action->execute($user1, $user2);
        $room2 = $action->execute($user2, $user1);

        expect($room1->id)->toBe($room2->id);
    });

    it('creates separate conversations for different user pairs', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $action = app(StartConversationAction::class);
        $room1 = $action->execute($user1, $user2);
        $room2 = $action->execute($user1, $user3);

        expect($room1->id)->not->toBe($room2->id);
    });
});

describe('GetUserConversationsAction', function (): void {
    it('returns all active conversations for a user', function (): void {
        $user = User::factory()->create();
        $otherUser1 = User::factory()->create();
        $otherUser2 = User::factory()->create();

        $startAction = app(StartConversationAction::class);
        $startAction->execute($user, $otherUser1);
        $startAction->execute($user, $otherUser2);

        $action = app(GetUserConversationsAction::class);
        $conversations = $action->execute($user);

        expect($conversations)->toHaveCount(2);
    });

    it('excludes blocked conversations', function (): void {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $startAction = app(StartConversationAction::class);
        $room = $startAction->execute($user, $otherUser);

        // Block the conversation
        $conversation = $room->conversations()->where('user_id', $user->id)->first();
        $conversation->setBlocked(true);

        $action = app(GetUserConversationsAction::class);
        $conversations = $action->execute($user);

        expect($conversations)->toHaveCount(0);
    });
});

describe('CreatePublicRoomAction', function (): void {
    it('allows admin to create public room', function (): void {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $action = app(CreatePublicRoomAction::class);
        $room = $action->execute($admin, 'Test Kafana');

        expect($room)->toBeInstanceOf(ChatRoom::class)
            ->and($room->type)->toBe(ChatRoomType::PUBLIC_KAFANA)
            ->and($room->creator_id)->toBe($admin->id);
    });

    it('allows moderator to create public room', function (): void {
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);

        $action = app(CreatePublicRoomAction::class);
        $room = $action->execute($moderator, 'Mod Kafana');

        expect($room->creator_id)->toBe($moderator->id);
    });

    it('allows creator to create public room', function (): void {
        $creator = User::factory()->create(['role' => UserRole::Creator]);

        $action = app(CreatePublicRoomAction::class);
        $room = $action->execute($creator, 'Creator Kafana');

        expect($room->creator_id)->toBe($creator->id);
    });

    it('rejects regular user from creating public room', function (): void {
        $user = User::factory()->create(['role' => UserRole::User]);

        $action = app(CreatePublicRoomAction::class);

        expect(fn () => $action->execute($user, 'User Kafana'))
            ->toThrow(\DomainException::class, 'Only admins, moderators, and creators can create public chat rooms.');
    });

    it('creates room with location', function (): void {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $location = Location::factory()->create(['city' => 'Test City']);

        $action = app(CreatePublicRoomAction::class);
        $room = $action->execute($admin, 'City Kafana', 'A kafana for the city', $location);

        expect($room->location_id)->toBe($location->id)
            ->and($room->description)->toBe('A kafana for the city');
    });
});

describe('Conversation Model', function (): void {
    it('tracks unread messages', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $room = ChatRoom::factory()->create(['type' => ChatRoomType::DIRECT_MESSAGE]);

        $conv1 = Conversation::factory()->inRoom($room)->forUser($user1)->create();
        $conv2 = Conversation::factory()->inRoom($room)->forUser($user2)->create();

        // User 2 sends messages
        Message::factory()->inRoom($room)->fromUser($user2)->count(3)->create();

        expect($conv1->hasUnread())->toBeTrue()
            ->and($conv1->unreadCount())->toBe(3);

        // User 1 marks as read
        $conv1->markAsRead();

        expect($conv1->hasUnread())->toBeFalse()
            ->and($conv1->unreadCount())->toBe(0);
    });

    it('toggles mute status', function (): void {
        $user = User::factory()->create();
        $room = ChatRoom::factory()->create(['type' => ChatRoomType::DIRECT_MESSAGE]);
        $conversation = Conversation::factory()->inRoom($room)->forUser($user)->create();

        expect($conversation->is_muted)->toBeFalse();

        $conversation->toggleMute();
        $conversation->refresh();

        expect($conversation->is_muted)->toBeTrue();

        $conversation->toggleMute();
        $conversation->refresh();

        expect($conversation->is_muted)->toBeFalse();
    });

    it('blocks and unblocks conversation', function (): void {
        $user = User::factory()->create();
        $room = ChatRoom::factory()->create(['type' => ChatRoomType::DIRECT_MESSAGE]);
        $conversation = Conversation::factory()->inRoom($room)->forUser($user)->create();

        $conversation->setBlocked(true);
        $conversation->refresh();

        expect($conversation->is_blocked)->toBeTrue();

        $conversation->setBlocked(false);
        $conversation->refresh();

        expect($conversation->is_blocked)->toBeFalse();
    });
});

describe('ChatRoom DM Methods', function (): void {
    it('identifies DM rooms correctly', function (): void {
        $dmRoom = ChatRoom::factory()->create(['type' => ChatRoomType::DIRECT_MESSAGE]);
        $kafanaRoom = ChatRoom::factory()->create(['type' => ChatRoomType::PUBLIC_KAFANA]);

        expect($dmRoom->isDirectMessage())->toBeTrue()
            ->and($kafanaRoom->isDirectMessage())->toBeFalse();
    });

    it('gets the other participant in a DM', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $room = ChatRoom::factory()->create(['type' => ChatRoomType::DIRECT_MESSAGE]);
        Conversation::factory()->inRoom($room)->forUser($user1)->create();
        Conversation::factory()->inRoom($room)->forUser($user2)->create();

        $otherUser = $room->getOtherParticipant($user1);

        expect($otherUser)->not->toBeNull()
            ->and($otherUser->id)->toBe($user2->id);
    });

    it('checks participant membership', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $room = ChatRoom::factory()->create(['type' => ChatRoomType::DIRECT_MESSAGE]);
        Conversation::factory()->inRoom($room)->forUser($user1)->create();
        Conversation::factory()->inRoom($room)->forUser($user2)->create();

        expect($room->hasParticipant($user1))->toBeTrue()
            ->and($room->hasParticipant($user2))->toBeTrue()
            ->and($room->hasParticipant($user3))->toBeFalse();
    });
});
