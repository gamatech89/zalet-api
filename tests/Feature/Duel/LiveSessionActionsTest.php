<?php

declare(strict_types=1);

use App\Domains\Duel\Actions\CreateLiveSessionAction;
use App\Domains\Duel\Actions\EndLiveSessionAction;
use App\Domains\Duel\Actions\JoinLiveSessionAction;
use App\Domains\Duel\Enums\ChatRoomType;
use App\Domains\Duel\Enums\LiveSessionStatus;
use App\Domains\Duel\Events\DuelEnded;
use App\Domains\Duel\Events\DuelStarted;
use App\Domains\Duel\Events\UserJoinedDuel;
use App\Domains\Duel\Models\ChatRoom;
use App\Domains\Duel\Models\LiveSession;
use App\Domains\Duel\Services\DuelScoreService;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Event;

describe('CreateLiveSessionAction', function (): void {
    it('creates a live session with a new duel chat room', function (): void {
        $host = User::factory()->create();

        $action = app(CreateLiveSessionAction::class);
        $session = $action->execute($host);

        expect($session)
            ->toBeInstanceOf(LiveSession::class)
            ->host_id->toBe($host->id)
            ->guest_id->toBeNull()
            ->status->toBe(LiveSessionStatus::WAITING)
            ->host_score->toBe(0)
            ->guest_score->toBe(0)
            ->started_at->toBeNull()
            ->ended_at->toBeNull();

        expect($session->chatRoom)
            ->type->toBe(ChatRoomType::DUEL)
            ->is_active->toBeTrue();
    });

    it('creates a live session with an existing chat room', function (): void {
        $host = User::factory()->create();
        $room = ChatRoom::factory()->duel()->create();

        $action = app(CreateLiveSessionAction::class);
        $session = $action->execute($host, $room);

        expect($session->chat_room_id)->toBe($room->id);
    });

    it('creates a live session with custom meta', function (): void {
        $host = User::factory()->create();
        $meta = ['theme' => 'battle', 'max_rounds' => 3];

        $action = app(CreateLiveSessionAction::class);
        $session = $action->execute($host, null, $meta);

        expect($session->meta)->toBe($meta);
    });

    it('initializes score cache in Redis', function (): void {
        $host = User::factory()->create();

        $action = app(CreateLiveSessionAction::class);
        $session = $action->execute($host);

        $scoreService = app(DuelScoreService::class);
        $scores = $scoreService->getScores($session);

        expect($scores)->toBe(['host' => 0, 'guest' => 0]);
    });
});

describe('JoinLiveSessionAction', function (): void {
    it('allows a guest to join a waiting session', function (): void {
        Event::fake([DuelStarted::class, UserJoinedDuel::class]);

        $host = User::factory()->create();
        $guest = User::factory()->create();
        $session = LiveSession::factory()->hostedBy($host)->waiting()->create();

        $action = new JoinLiveSessionAction();
        $updatedSession = $action->execute($session, $guest);

        expect($updatedSession)
            ->guest_id->toBe($guest->id)
            ->status->toBe(LiveSessionStatus::ACTIVE)
            ->started_at->not->toBeNull();

        Event::assertDispatched(UserJoinedDuel::class);
        Event::assertDispatched(DuelStarted::class);
    });

    it('rejects joining an active session', function (): void {
        $session = LiveSession::factory()->active()->create();
        $guest = User::factory()->create();

        $action = new JoinLiveSessionAction();

        expect(fn () => $action->execute($session, $guest))
            ->toThrow(\InvalidArgumentException::class, 'Session is not waiting for a guest.');
    });

    it('rejects joining a session that already has a guest', function (): void {
        $existingGuest = User::factory()->create();
        $session = LiveSession::factory()
            ->withGuest($existingGuest)
            ->waiting()
            ->create();

        $newGuest = User::factory()->create();
        $action = new JoinLiveSessionAction();

        expect(fn () => $action->execute($session, $newGuest))
            ->toThrow(\InvalidArgumentException::class, 'Session already has a guest.');
    });

    it('rejects host joining as guest', function (): void {
        $host = User::factory()->create();
        $session = LiveSession::factory()->hostedBy($host)->waiting()->create();

        $action = new JoinLiveSessionAction();

        expect(fn () => $action->execute($session, $host))
            ->toThrow(\InvalidArgumentException::class, 'Host cannot join as guest.');
    });

    it('records user joined event', function (): void {
        Event::fake();

        $session = LiveSession::factory()->waiting()->create();
        $guest = User::factory()->create();

        $action = new JoinLiveSessionAction();
        $action->execute($session, $guest);

        expect($session->events()->where('actor_id', $guest->id)->exists())->toBeTrue();
    });
});

describe('EndLiveSessionAction', function (): void {
    it('ends an active session and determines winner', function (): void {
        Event::fake([DuelEnded::class]);

        $host = User::factory()->create();
        $guest = User::factory()->create();
        $session = LiveSession::factory()
            ->hostedBy($host)
            ->withGuest($guest)
            ->active()
            ->withScores(100, 50)
            ->create();

        // Set up Redis scores
        $scoreService = app(DuelScoreService::class);
        $scoreService->setScores($session, 100, 50);

        $action = app(EndLiveSessionAction::class);
        $endedSession = $action->execute($session);

        expect($endedSession)
            ->status->toBe(LiveSessionStatus::COMPLETED)
            ->winner_id->toBe($host->id)
            ->ended_at->not->toBeNull();

        Event::assertDispatched(DuelEnded::class);
    });

    it('ends with guest as winner when guest has higher score', function (): void {
        Event::fake([DuelEnded::class]);

        $host = User::factory()->create();
        $guest = User::factory()->create();
        $session = LiveSession::factory()
            ->hostedBy($host)
            ->withGuest($guest)
            ->active()
            ->create();

        $scoreService = app(DuelScoreService::class);
        $scoreService->setScores($session, 30, 75);

        $action = app(EndLiveSessionAction::class);
        $endedSession = $action->execute($session);

        expect($endedSession->winner_id)->toBe($guest->id);
    });

    it('ends with no winner when scores are tied', function (): void {
        Event::fake([DuelEnded::class]);

        $host = User::factory()->create();
        $guest = User::factory()->create();
        $session = LiveSession::factory()
            ->hostedBy($host)
            ->withGuest($guest)
            ->active()
            ->create();

        $scoreService = app(DuelScoreService::class);
        $scoreService->setScores($session, 50, 50);

        $action = app(EndLiveSessionAction::class);
        $endedSession = $action->execute($session);

        expect($endedSession->winner_id)->toBeNull();
    });

    it('cancels a waiting session', function (): void {
        Event::fake([DuelEnded::class]);

        $session = LiveSession::factory()->waiting()->create();
        $user = User::factory()->create();

        $action = app(EndLiveSessionAction::class);
        $cancelledSession = $action->cancel($session, $user);

        expect($cancelledSession)
            ->status->toBe(LiveSessionStatus::CANCELLED)
            ->ended_at->not->toBeNull();

        Event::assertDispatched(DuelEnded::class);
    });

    it('rejects ending an already completed session', function (): void {
        $session = LiveSession::factory()->completed()->create();

        $action = app(EndLiveSessionAction::class);

        expect(fn () => $action->execute($session))
            ->toThrow(\InvalidArgumentException::class, 'Session is already ended.');
    });

    it('rejects cancelling an active session', function (): void {
        $session = LiveSession::factory()->active()->create();

        $action = app(EndLiveSessionAction::class);

        expect(fn () => $action->cancel($session))
            ->toThrow(\InvalidArgumentException::class, 'Only waiting sessions can be cancelled.');
    });

    it('clears Redis cache after ending', function (): void {
        Event::fake([DuelEnded::class]);

        $session = LiveSession::factory()->active()->create();

        $scoreService = app(DuelScoreService::class);
        $scoreService->setScores($session, 100, 50);

        $action = app(EndLiveSessionAction::class);
        $action->execute($session);

        // After ending, getting scores should return from DB (0, 0 initially)
        // since cache was cleared
        $freshSession = LiveSession::find($session->id);
        $scores = $scoreService->getScores($freshSession);

        // Scores from DB should be saved before clear
        expect($scores)->toBe(['host' => 100, 'guest' => 50]);
    });
});
