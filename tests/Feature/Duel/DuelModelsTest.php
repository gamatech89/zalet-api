<?php

declare(strict_types=1);

use App\Domains\Duel\Enums\ChatRoomType;
use App\Domains\Duel\Enums\DuelEventType;
use App\Domains\Duel\Enums\LiveSessionStatus;
use App\Domains\Duel\Enums\MessageType;
use App\Domains\Duel\Models\ChatRoom;
use App\Domains\Duel\Models\DuelEvent;
use App\Domains\Duel\Models\LiveSession;
use App\Domains\Duel\Models\Message;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\User;

describe('ChatRoom Model', function (): void {
    it('creates a chat room with factory', function (): void {
        $room = ChatRoom::factory()->create();

        expect($room)
            ->toBeInstanceOf(ChatRoom::class)
            ->uuid->not->toBeEmpty()
            ->name->not->toBeEmpty()
            ->slug->not->toBeEmpty()
            ->is_active->toBeTrue();
    });

    it('creates different room types', function (): void {
        $kafana = ChatRoom::factory()->kafana()->create();
        $private = ChatRoom::factory()->private()->create();
        $duel = ChatRoom::factory()->duel()->create();

        expect($kafana->type)->toBe(ChatRoomType::PUBLIC_KAFANA);
        expect($private->type)->toBe(ChatRoomType::PRIVATE);
        expect($duel->type)->toBe(ChatRoomType::DUEL);
    });

    it('belongs to a location', function (): void {
        $location = Location::factory()->create();
        $room = ChatRoom::factory()->forLocation($location)->create();

        expect($room->location->id)->toBe($location->id);
    });

    it('has many messages', function (): void {
        $room = ChatRoom::factory()->create();
        Message::factory()->count(3)->inRoom($room)->create();

        expect($room->messages)->toHaveCount(3);
    });

    it('has many live sessions', function (): void {
        $room = ChatRoom::factory()->duel()->create();
        LiveSession::factory()->count(2)->inRoom($room)->create();

        expect($room->liveSessions)->toHaveCount(2);
    });

    it('can be deactivated', function (): void {
        $room = ChatRoom::factory()->inactive()->create();

        expect($room->is_active)->toBeFalse();
    });

    it('generates unique slugs', function (): void {
        $room1 = ChatRoom::factory()->create(['name' => 'Test Room']);
        $room2 = ChatRoom::factory()->create(['name' => 'Test Room']);

        expect($room1->slug)->not->toBe($room2->slug);
    });
});

describe('LiveSession Model', function (): void {
    it('creates a live session with factory', function (): void {
        $session = LiveSession::factory()->create();

        expect($session)
            ->toBeInstanceOf(LiveSession::class)
            ->uuid->not->toBeEmpty()
            ->status->toBe(LiveSessionStatus::WAITING);
    });

    it('belongs to a chat room', function (): void {
        $room = ChatRoom::factory()->create();
        $session = LiveSession::factory()->inRoom($room)->create();

        expect($session->chatRoom->id)->toBe($room->id);
    });

    it('belongs to a host', function (): void {
        $host = User::factory()->create();
        $session = LiveSession::factory()->hostedBy($host)->create();

        expect($session->host->id)->toBe($host->id);
    });

    it('belongs to a guest optionally', function (): void {
        $guest = User::factory()->create();
        $session = LiveSession::factory()->withGuest($guest)->create();

        expect($session->guest->id)->toBe($guest->id);
    });

    it('has status transitions', function (): void {
        $waiting = LiveSession::factory()->waiting()->create();
        $active = LiveSession::factory()->active()->create();
        $paused = LiveSession::factory()->paused()->create();
        $completed = LiveSession::factory()->completed()->create();
        $cancelled = LiveSession::factory()->cancelled()->create();

        expect($waiting->status)->toBe(LiveSessionStatus::WAITING);
        expect($active->status)->toBe(LiveSessionStatus::ACTIVE);
        expect($paused->status)->toBe(LiveSessionStatus::PAUSED);
        expect($completed->status)->toBe(LiveSessionStatus::COMPLETED);
        expect($cancelled->status)->toBe(LiveSessionStatus::CANCELLED);
    });

    it('tracks scores', function (): void {
        $session = LiveSession::factory()->withScores(100, 75)->create();

        expect($session->host_score)->toBe(100);
        expect($session->guest_score)->toBe(75);
    });

    it('has a winner when completed', function (): void {
        $winner = User::factory()->create();
        $session = LiveSession::factory()->wonBy($winner)->create();

        expect($session->winner->id)->toBe($winner->id);
        expect($session->status)->toBe(LiveSessionStatus::COMPLETED);
    });

    it('has many duel events', function (): void {
        $session = LiveSession::factory()->create();
        DuelEvent::factory()->count(5)->forSession($session)->create();

        expect($session->events)->toHaveCount(5);
    });

    it('checks if session is active', function (): void {
        $active = LiveSession::factory()->active()->create();
        $waiting = LiveSession::factory()->waiting()->create();
        $completed = LiveSession::factory()->completed()->create();

        expect($active->isActive())->toBeTrue();
        expect($waiting->isActive())->toBeFalse();
        expect($completed->isActive())->toBeFalse();
    });

    it('checks if session is ended', function (): void {
        $completed = LiveSession::factory()->completed()->create();
        $cancelled = LiveSession::factory()->cancelled()->create();
        $active = LiveSession::factory()->active()->create();

        expect($completed->isEnded())->toBeTrue();
        expect($cancelled->isEnded())->toBeTrue();
        expect($active->isEnded())->toBeFalse();
    });
});

describe('DuelEvent Model', function (): void {
    it('creates a duel event with factory', function (): void {
        $event = DuelEvent::factory()->create();

        expect($event)
            ->toBeInstanceOf(DuelEvent::class)
            ->event_type->not->toBeNull();
    });

    it('belongs to a live session', function (): void {
        $session = LiveSession::factory()->create();
        $event = DuelEvent::factory()->forSession($session)->create();

        expect($event->liveSession->id)->toBe($session->id);
    });

    it('belongs to a user', function (): void {
        $user = User::factory()->create();
        $event = DuelEvent::factory()->byActor($user)->create();

        expect($event->actor->id)->toBe($user->id);
    });

    it('creates gift sent event', function (): void {
        $event = DuelEvent::factory()->giftSent('rose', 10)->create();

        expect($event->event_type)->toBe(DuelEventType::GIFT_SENT);
        expect($event->payload['gift_slug'])->toBe('rose');
        expect($event->payload['credit_value'])->toBe(10);
    });

    it('creates score updated event', function (): void {
        $event = DuelEvent::factory()->scoreUpdated(100, 75)->create();

        expect($event->event_type)->toBe(DuelEventType::SCORE_UPDATED);
        expect($event->payload['host_score'])->toBe(100);
        expect($event->payload['guest_score'])->toBe(75);
    });

    it('creates user joined event', function (): void {
        $event = DuelEvent::factory()->userJoined()->create();

        expect($event->event_type)->toBe(DuelEventType::USER_JOINED);
    });

    it('creates user left event', function (): void {
        $event = DuelEvent::factory()->userLeft()->create();

        expect($event->event_type)->toBe(DuelEventType::USER_LEFT);
    });

    it('creates round ended event', function (): void {
        $event = DuelEvent::factory()->roundEnded(1, 'user-uuid')->create();

        expect($event->event_type)->toBe(DuelEventType::ROUND_ENDED);
        expect($event->payload['round_number'])->toBe(1);
    });

    it('creates duel ended event', function (): void {
        $event = DuelEvent::factory()->duelEnded('winner-uuid')->create();

        expect($event->event_type)->toBe(DuelEventType::DUEL_ENDED);
    });
});

describe('Message Model', function (): void {
    it('creates a message with factory', function (): void {
        $message = Message::factory()->create();

        expect($message)
            ->toBeInstanceOf(Message::class)
            ->uuid->not->toBeEmpty()
            ->type->toBe(MessageType::TEXT);
    });

    it('belongs to a chat room', function (): void {
        $room = ChatRoom::factory()->create();
        $message = Message::factory()->inRoom($room)->create();

        expect($message->chatRoom->id)->toBe($room->id);
    });

    it('belongs to a user', function (): void {
        $user = User::factory()->create();
        $message = Message::factory()->fromUser($user)->create();

        expect($message->user->id)->toBe($user->id);
    });

    it('creates text message', function (): void {
        $message = Message::factory()->text('Hello world!')->create();

        expect($message->type)->toBe(MessageType::TEXT);
        expect($message->content)->toBe('Hello world!');
    });

    it('creates gift message', function (): void {
        $recipient = User::factory()->create();
        $message = Message::factory()->gift('rose', 10, $recipient)->create();

        expect($message->type)->toBe(MessageType::GIFT);
        expect($message->meta['gift_slug'])->toBe('rose');
        expect($message->meta['recipient_id'])->toBe($recipient->id);
    });

    it('creates system message without user', function (): void {
        $message = Message::factory()->system('Welcome to the chat!')->create();

        expect($message->type)->toBe(MessageType::SYSTEM);
        expect($message->user_id)->toBeNull();
        expect($message->content)->toBe('Welcome to the chat!');
    });

    it('stores metadata', function (): void {
        $meta = ['highlighted' => true, 'badge' => 'vip'];
        $message = Message::factory()->withMeta($meta)->create();

        expect($message->meta)->toBe($meta);
    });
});
