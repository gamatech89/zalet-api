<?php

declare(strict_types=1);

use App\Domains\Duel\Actions\SendDuelGiftAction;
use App\Domains\Duel\Actions\SendMessageAction;
use App\Domains\Duel\Enums\LiveSessionStatus;
use App\Domains\Duel\Enums\MessageType;
use App\Domains\Duel\Events\DuelGiftSent;
use App\Domains\Duel\Events\MessageSent;
use App\Domains\Duel\Models\ChatRoom;
use App\Domains\Duel\Models\LiveSession;
use App\Domains\Duel\Services\DuelScoreService;
use App\Domains\Identity\Models\User;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Models\Wallet;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;

describe('SendDuelGiftAction', function (): void {
    afterEach(function (): void {
        // Clean up Redis keys for this test only (don't flush entire database)
        $keys = Redis::keys('duel:scores:*');
        if (! empty($keys)) {
            Redis::del($keys);
        }
    });

    it('sends a gift and updates duel scores', function (): void {
        Event::fake([DuelGiftSent::class, MessageSent::class]);

        // Setup users with wallets
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $viewer = User::factory()->create();

        Wallet::factory()->withBalance(1000)->for($host)->create();
        Wallet::factory()->withBalance(1000)->for($guest)->create();
        Wallet::factory()->withBalance(1000)->for($viewer)->create();

        // Create active session
        $session = LiveSession::factory()
            ->hostedBy($host)
            ->withGuest($guest)
            ->active()
            ->create();

        // Initialize scores
        $scoreService = app(DuelScoreService::class);
        $scoreService->initializeSession($session);

        // Viewer sends gift to host
        $action = app(SendDuelGiftAction::class);
        $action->execute($session, $viewer, $host, 'rose', 1);

        // Check scores updated (rose = 10 credits)
        $scores = $scoreService->getScores($session);
        expect($scores['host'])->toBe(10);
        expect($scores['guest'])->toBe(0);

        // Check events were recorded
        expect($session->events()->count())->toBe(1);

        // Check message was created
        expect($session->chatRoom->messages()->count())->toBe(1);

        Event::assertDispatched(DuelGiftSent::class);
        Event::assertDispatched(MessageSent::class);
    });

    it('adds gift value to guest score when gift sent to guest', function (): void {
        Event::fake([DuelGiftSent::class, MessageSent::class]);

        $host = User::factory()->create();
        $guest = User::factory()->create();
        $viewer = User::factory()->create();

        Wallet::factory()->withBalance(1000)->for($viewer)->create();
        Wallet::factory()->withBalance(0)->for($guest)->create();

        $session = LiveSession::factory()
            ->hostedBy($host)
            ->withGuest($guest)
            ->active()
            ->create();

        $scoreService = app(DuelScoreService::class);
        $scoreService->initializeSession($session);

        $action = app(SendDuelGiftAction::class);
        $action->execute($session, $viewer, $guest, 'crown', 1);

        // Crown = 100 credits
        $scores = $scoreService->getScores($session);
        expect($scores['host'])->toBe(0);
        expect($scores['guest'])->toBe(100);
    });

    it('handles multiple gifts from multiple viewers', function (): void {
        Event::fake([DuelGiftSent::class, MessageSent::class]);

        $host = User::factory()->create();
        $guest = User::factory()->create();
        $viewer1 = User::factory()->create();
        $viewer2 = User::factory()->create();

        Wallet::factory()->withBalance(1000)->for($viewer1)->create();
        Wallet::factory()->withBalance(1000)->for($viewer2)->create();
        Wallet::factory()->withBalance(0)->for($host)->create();
        Wallet::factory()->withBalance(0)->for($guest)->create();

        $session = LiveSession::factory()
            ->hostedBy($host)
            ->withGuest($guest)
            ->active()
            ->create();

        $scoreService = app(DuelScoreService::class);
        $scoreService->initializeSession($session);

        $action = app(SendDuelGiftAction::class);

        // Viewer 1 sends to host: 25 credits (heart)
        $action->execute($session, $viewer1, $host, 'heart', 1);

        // Viewer 2 sends to guest: 10 credits (rose)
        $action->execute($session, $viewer2, $guest, 'rose', 1);

        // Viewer 1 sends to host again: 5 credits (rakija)
        $action->execute($session, $viewer1, $host, 'rakija', 1);

        $scores = $scoreService->getScores($session);
        expect($scores['host'])->toBe(30); // 25 + 5
        expect($scores['guest'])->toBe(10);
    });

    it('rejects sending gifts to non-active sessions', function (): void {
        $host = User::factory()->create();
        $viewer = User::factory()->create();

        Wallet::factory()->withBalance(1000)->for($viewer)->create();

        $session = LiveSession::factory()
            ->hostedBy($host)
            ->waiting()
            ->create();

        $action = app(SendDuelGiftAction::class);

        expect(fn () => $action->execute($session, $viewer, $host, 'rose', 1))
            ->toThrow(\InvalidArgumentException::class, 'Can only send gifts during active duels.');
    });

    it('rejects sending gifts to non-participants', function (): void {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $viewer = User::factory()->create();
        $randomUser = User::factory()->create();

        Wallet::factory()->withBalance(1000)->for($viewer)->create();

        $session = LiveSession::factory()
            ->hostedBy($host)
            ->withGuest($guest)
            ->active()
            ->create();

        $action = app(SendDuelGiftAction::class);

        expect(fn () => $action->execute($session, $viewer, $randomUser, 'rose', 1))
            ->toThrow(\InvalidArgumentException::class, 'Recipient must be a duel participant.');
    });

    it('deducts credits from sender wallet', function (): void {
        Event::fake([DuelGiftSent::class, MessageSent::class]);

        $host = User::factory()->create();
        $viewer = User::factory()->create();

        Wallet::factory()->withBalance(100)->for($viewer)->create();
        Wallet::factory()->withBalance(0)->for($host)->create();

        $session = LiveSession::factory()
            ->hostedBy($host)
            ->withGuest(User::factory()->create())
            ->active()
            ->create();

        $scoreService = app(DuelScoreService::class);
        $scoreService->initializeSession($session);

        $action = app(SendDuelGiftAction::class);
        $action->execute($session, $viewer, $host, 'heart', 1); // 25 credits

        expect($viewer->wallet->fresh()->balance)->toBe(75);
    });

    it('credits recipient wallet', function (): void {
        Event::fake([DuelGiftSent::class, MessageSent::class]);

        $host = User::factory()->create();
        $viewer = User::factory()->create();

        Wallet::factory()->withBalance(100)->for($viewer)->create();
        Wallet::factory()->withBalance(50)->for($host)->create();

        $session = LiveSession::factory()
            ->hostedBy($host)
            ->withGuest(User::factory()->create())
            ->active()
            ->create();

        $scoreService = app(DuelScoreService::class);
        $scoreService->initializeSession($session);

        $action = app(SendDuelGiftAction::class);
        $action->execute($session, $viewer, $host, 'rose', 1); // 10 credits

        expect($host->wallet->fresh()->balance)->toBe(60);
    });
});

describe('SendMessageAction', function (): void {
    it('sends a text message to a chat room', function (): void {
        Event::fake([MessageSent::class]);

        $user = User::factory()->create();
        $room = ChatRoom::factory()->create();

        $action = new SendMessageAction();
        $message = $action->execute($room, $user, 'Hello world!');

        expect($message)
            ->chat_room_id->toBe($room->id)
            ->user_id->toBe($user->id)
            ->type->toBe(MessageType::TEXT)
            ->content->toBe('Hello world!');

        Event::assertDispatched(MessageSent::class);
    });

    it('sends a system message without user', function (): void {
        Event::fake([MessageSent::class]);

        $room = ChatRoom::factory()->create();

        $action = new SendMessageAction();
        $message = $action->sendSystemMessage($room, 'User joined the room');

        expect($message)
            ->user_id->toBeNull()
            ->type->toBe(MessageType::SYSTEM)
            ->content->toBe('User joined the room');

        Event::assertDispatched(MessageSent::class);
    });

    it('rejects messages to inactive rooms', function (): void {
        $user = User::factory()->create();
        $room = ChatRoom::factory()->inactive()->create();

        $action = new SendMessageAction();

        expect(fn () => $action->execute($room, $user, 'Hello'))
            ->toThrow(\InvalidArgumentException::class, 'Cannot send messages to inactive rooms.');
    });

    it('rejects messages that are too long', function (): void {
        $user = User::factory()->create();
        $room = ChatRoom::factory()->create();
        $longMessage = str_repeat('a', 1001);

        $action = new SendMessageAction();

        expect(fn () => $action->execute($room, $user, $longMessage))
            ->toThrow(\InvalidArgumentException::class, 'Message content too long.');
    });

    it('includes metadata in message', function (): void {
        Event::fake([MessageSent::class]);

        $user = User::factory()->create();
        $room = ChatRoom::factory()->create();
        $meta = ['highlighted' => true, 'color' => 'gold'];

        $action = new SendMessageAction();
        $message = $action->execute($room, $user, 'Special message!', $meta);

        expect($message->meta)->toBe($meta);
    });
});
