<?php

declare(strict_types=1);

use App\Domains\Duel\Enums\LiveSessionStatus;
use App\Domains\Duel\Events\DuelEnded;
use App\Domains\Duel\Events\DuelGiftSent;
use App\Domains\Duel\Events\DuelStarted;
use App\Domains\Duel\Events\UserJoinedDuel;
use App\Domains\Duel\Models\ChatRoom;
use App\Domains\Duel\Models\LiveSession;
use App\Domains\Duel\Services\DuelScoreService;
use App\Domains\Identity\Models\User;
use App\Domains\Wallet\Models\Wallet;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

afterEach(function (): void {
    // Clean up Redis keys for this test only (don't flush entire database)
    $keys = Redis::keys('duel:scores:*');
    if (! empty($keys)) {
        Redis::del($keys);
    }
});

describe('LiveSession Controller', function (): void {
    describe('GET /api/v1/live-sessions', function (): void {
        it('lists live sessions', function (): void {
            $user = User::factory()->create();
            $sessions = LiveSession::factory()->count(3)->create();

            $response = $this->actingAs($user)->getJson('/api/v1/live-sessions');

            $response->assertOk()
                ->assertJsonStructure([
                    'data' => [
                        '*' => ['id', 'uuid', 'status', 'hostScore', 'guestScore'],
                    ],
                ]);

            // At least the 3 sessions we created should be in paginated results
            expect(count($response->json('data')))->toBeGreaterThanOrEqual(min(3, 20));
        });

        it('filters by status', function (): void {
            $user = User::factory()->create();
            $waiting = LiveSession::factory()->waiting()->count(2)->create();
            LiveSession::factory()->active()->count(3)->create();

            $response = $this->actingAs($user)->getJson('/api/v1/live-sessions?status=waiting');

            $response->assertOk();

            // All returned items should have status 'waiting'
            foreach ($response->json('data') as $session) {
                expect($session['status'])->toBe('waiting');
            }
        });

        it('filters by host_id', function (): void {
            $user = User::factory()->create();
            $host = User::factory()->create();
            LiveSession::factory()->hostedBy($host)->count(2)->create();
            LiveSession::factory()->count(3)->create();

            $response = $this->actingAs($user)->getJson("/api/v1/live-sessions?host_id={$host->id}");

            $response->assertOk();
            expect($response->json('data'))->toHaveCount(2);
        });
    });

    describe('GET /api/v1/live-sessions/lobby', function (): void {
        it('returns waiting and active sessions', function (): void {
            $user = User::factory()->create();
            $waiting = LiveSession::factory()->waiting()->count(2)->create();
            $active = LiveSession::factory()->active()->count(2)->create();
            LiveSession::factory()->completed()->count(3)->create();

            $response = $this->actingAs($user)->getJson('/api/v1/live-sessions/lobby');

            $response->assertOk();

            // Lobby should return only waiting and active sessions
            // Verify that all returned sessions have correct status
            foreach ($response->json('data') as $session) {
                expect($session['status'])->toBeIn(['waiting', 'active']);
            }
        });
    });

    describe('POST /api/v1/live-sessions', function (): void {
        it('creates a new live session', function (): void {
            $host = User::factory()->create();

            $response = $this->actingAs($host)->postJson('/api/v1/live-sessions');

            $response->assertCreated()
                ->assertJsonStructure([
                    'data' => ['id', 'uuid', 'status', 'host'],
                    'message',
                ]);

            expect($response->json('data.status'))->toBe('waiting');
            expect($response->json('data.host.id'))->toBe($host->id);
        });

        it('creates session with existing chat room', function (): void {
            $host = User::factory()->create();
            $room = ChatRoom::factory()->duel()->create();

            $response = $this->actingAs($host)->postJson('/api/v1/live-sessions', [
                'chat_room_id' => $room->id,
            ]);

            $response->assertCreated();
            expect($response->json('data.chatRoom.id'))->toBe($room->id);
        });

        it('creates session with custom meta', function (): void {
            $host = User::factory()->create();

            $response = $this->actingAs($host)->postJson('/api/v1/live-sessions', [
                'meta' => ['title' => 'Epic Battle', 'rounds' => 3],
            ]);

            $response->assertCreated();

            $session = LiveSession::where('host_id', $host->id)->first();
            expect($session->meta['title'])->toBe('Epic Battle');
        });
    });

    describe('GET /api/v1/live-sessions/{uuid}', function (): void {
        it('returns a specific session', function (): void {
            $user = User::factory()->create();
            $session = LiveSession::factory()->create();

            $response = $this->actingAs($user)->getJson("/api/v1/live-sessions/{$session->uuid}");

            $response->assertOk()
                ->assertJsonPath('data.uuid', $session->uuid);
        });

        it('returns scores for active session', function (): void {
            $user = User::factory()->create();
            $session = LiveSession::factory()->active()->create();

            $scoreService = app(DuelScoreService::class);
            $scoreService->initializeSession($session);
            $scoreService->setScores($session, 100, 75);

            $response = $this->actingAs($user)->getJson("/api/v1/live-sessions/{$session->uuid}");

            $response->assertOk()
                ->assertJsonPath('scores.host', 100)
                ->assertJsonPath('scores.guest', 75);
        });

        it('returns 404 for non-existent session', function (): void {
            $user = User::factory()->create();
            $fakeUuid = '00000000-0000-0000-0000-000000000000';

            $response = $this->actingAs($user)->getJson("/api/v1/live-sessions/{$fakeUuid}");

            $response->assertNotFound();
        });
    });

    describe('POST /api/v1/live-sessions/{uuid}/join', function (): void {
        it('allows guest to join waiting session', function (): void {
            Event::fake([DuelStarted::class, UserJoinedDuel::class]);

            $host = User::factory()->create();
            $guest = User::factory()->create();
            $session = LiveSession::factory()->hostedBy($host)->waiting()->create();

            $response = $this->actingAs($guest)->postJson("/api/v1/live-sessions/{$session->uuid}/join");

            $response->assertOk()
                ->assertJsonPath('data.status', 'active')
                ->assertJsonPath('data.guest.id', $guest->id);

            Event::assertDispatched(DuelStarted::class);
        });

        it('rejects joining active session', function (): void {
            $guest = User::factory()->create();
            $session = LiveSession::factory()->active()->create();

            $response = $this->actingAs($guest)->postJson("/api/v1/live-sessions/{$session->uuid}/join");

            $response->assertUnprocessable();
        });

        it('rejects host joining own session', function (): void {
            $host = User::factory()->create();
            $session = LiveSession::factory()->hostedBy($host)->waiting()->create();

            $response = $this->actingAs($host)->postJson("/api/v1/live-sessions/{$session->uuid}/join");

            $response->assertUnprocessable();
        });
    });

    describe('POST /api/v1/live-sessions/{uuid}/end', function (): void {
        it('host can end active session', function (): void {
            Event::fake([DuelEnded::class]);

            $host = User::factory()->create();
            $guest = User::factory()->create();
            $session = LiveSession::factory()
                ->hostedBy($host)
                ->withGuest($guest)
                ->active()
                ->create([
                    'host_score' => 100,
                    'guest_score' => 75,
                ]);

            $response = $this->actingAs($host)->postJson("/api/v1/live-sessions/{$session->uuid}/end");

            $response->assertOk()
                ->assertJsonPath('data.status', 'completed');

            Event::assertDispatched(DuelEnded::class);
        });

        it('host can cancel waiting session', function (): void {
            $host = User::factory()->create();
            $session = LiveSession::factory()->hostedBy($host)->waiting()->create();

            $response = $this->actingAs($host)->postJson("/api/v1/live-sessions/{$session->uuid}/end");

            $response->assertOk()
                ->assertJsonPath('data.status', 'cancelled');
        });

        it('non-host cannot end session', function (): void {
            $host = User::factory()->create();
            $otherUser = User::factory()->create();
            $session = LiveSession::factory()->hostedBy($host)->active()->create();

            $response = $this->actingAs($otherUser)->postJson("/api/v1/live-sessions/{$session->uuid}/end");

            $response->assertForbidden();
        });
    });

    describe('GET /api/v1/live-sessions/{uuid}/scores', function (): void {
        it('returns real-time scores', function (): void {
            $user = User::factory()->create();
            $session = LiveSession::factory()->active()->create();

            $scoreService = app(DuelScoreService::class);
            $scoreService->initializeSession($session);
            $scoreService->setScores($session, 150, 100);

            $response = $this->actingAs($user)->getJson("/api/v1/live-sessions/{$session->uuid}/scores");

            $response->assertOk()
                ->assertJsonPath('data.hostScore', 150)
                ->assertJsonPath('data.guestScore', 100)
                ->assertJsonPath('data.leader', 'host')
                ->assertJsonPath('data.isTied', false)
                ->assertJsonPath('data.difference', 50);
        });
    });

    describe('POST /api/v1/live-sessions/{uuid}/gift', function (): void {
        it('sends gift during active duel', function (): void {
            Event::fake([DuelGiftSent::class]);

            $host = User::factory()->create();
            $guest = User::factory()->create();
            $viewer = User::factory()->create();

            Wallet::factory()->withBalance(1000)->for($viewer)->create();
            Wallet::factory()->for($host)->create();

            $session = LiveSession::factory()
                ->hostedBy($host)
                ->withGuest($guest)
                ->active()
                ->create();

            $scoreService = app(DuelScoreService::class);
            $scoreService->initializeSession($session);

            $response = $this->actingAs($viewer)->postJson("/api/v1/live-sessions/{$session->uuid}/gift", [
                'recipient_id' => $host->id,
                'gift_slug' => 'rose',
                'quantity' => 1,
            ]);

            $response->assertOk()
                ->assertJsonPath('scores.hostScore', 10); // rose = 10 credits
        });

        it('rejects gift to non-active session', function (): void {
            $viewer = User::factory()->create();
            $session = LiveSession::factory()->waiting()->create();

            $response = $this->actingAs($viewer)->postJson("/api/v1/live-sessions/{$session->uuid}/gift", [
                'recipient_id' => $session->host_id,
                'gift_slug' => 'rose',
            ]);

            $response->assertUnprocessable();
        });
    });

    describe('GET /api/v1/live-sessions/{uuid}/events', function (): void {
        it('returns session events', function (): void {
            $user = User::factory()->create();
            $session = LiveSession::factory()->active()->create();

            // Create some events via factory
            \App\Domains\Duel\Models\DuelEvent::factory()
                ->forSession($session)
                ->count(5)
                ->create();

            $response = $this->actingAs($user)->getJson("/api/v1/live-sessions/{$session->uuid}/events");

            $response->assertOk()
                ->assertJsonStructure([
                    'data' => [
                        '*' => ['id', 'eventType', 'payload', 'createdAt'],
                    ],
                ]);

            expect($response->json('data'))->toHaveCount(5);
        });
    });
});
