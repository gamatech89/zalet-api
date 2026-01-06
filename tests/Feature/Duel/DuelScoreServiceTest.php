<?php

declare(strict_types=1);

use App\Domains\Duel\Enums\LiveSessionStatus;
use App\Domains\Duel\Events\DuelScoreUpdated;
use App\Domains\Duel\Models\LiveSession;
use App\Domains\Duel\Services\DuelScoreService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;

describe('DuelScoreService', function (): void {
    afterEach(function (): void {
        // Clean up Redis keys for this test only (don't flush entire database)
        $keys = Redis::keys('duel:scores:*');
        if (! empty($keys)) {
            Redis::del($keys);
        }
    });

    it('initializes session with zero scores', function (): void {
        $session = LiveSession::factory()->create();
        $service = app(DuelScoreService::class);

        $service->initializeSession($session);
        $scores = $service->getScores($session);

        expect($scores)->toBe(['host' => 0, 'guest' => 0]);
    });

    it('sets scores directly', function (): void {
        $session = LiveSession::factory()->create();
        $service = app(DuelScoreService::class);

        $service->setScores($session, 100, 75);
        $scores = $service->getScores($session);

        expect($scores)->toBe(['host' => 100, 'guest' => 75]);
    });

    it('adds points to host score', function (): void {
        Event::fake([DuelScoreUpdated::class]);

        $session = LiveSession::factory()->active()->create();
        $service = app(DuelScoreService::class);

        $service->initializeSession($session);
        $service->addPoints($session, 'host', 25);
        $scores = $service->getScores($session);

        expect($scores['host'])->toBe(25);
        expect($scores['guest'])->toBe(0);

        Event::assertDispatched(DuelScoreUpdated::class);
    });

    it('adds points to guest score', function (): void {
        Event::fake([DuelScoreUpdated::class]);

        $session = LiveSession::factory()->active()->create();
        $service = app(DuelScoreService::class);

        $service->initializeSession($session);
        $service->addPoints($session, 'guest', 50);
        $scores = $service->getScores($session);

        expect($scores['host'])->toBe(0);
        expect($scores['guest'])->toBe(50);
    });

    it('accumulates points correctly', function (): void {
        Event::fake([DuelScoreUpdated::class]);

        $session = LiveSession::factory()->active()->create();
        $service = app(DuelScoreService::class);

        $service->initializeSession($session);
        $service->addPoints($session, 'host', 10);
        $service->addPoints($session, 'host', 15);
        $service->addPoints($session, 'guest', 20);
        $service->addPoints($session, 'host', 5);

        $scores = $service->getScores($session);

        expect($scores['host'])->toBe(30);
        expect($scores['guest'])->toBe(20);
    });

    it('throws exception for invalid party', function (): void {
        $session = LiveSession::factory()->create();
        $service = app(DuelScoreService::class);

        expect(fn () => $service->addPoints($session, 'invalid', 10))
            ->toThrow(\InvalidArgumentException::class, 'Party must be "host" or "guest"');
    });

    it('resets scores to zero', function (): void {
        $session = LiveSession::factory()->create();
        $service = app(DuelScoreService::class);

        $service->setScores($session, 100, 50);
        $service->resetScores($session);
        $scores = $service->getScores($session);

        expect($scores)->toBe(['host' => 0, 'guest' => 0]);
    });

    it('clears cache', function (): void {
        $session = LiveSession::factory()->create();
        $service = app(DuelScoreService::class);

        $service->setScores($session, 100, 50);
        $service->clearCache($session);

        // After clearing, it should reload from DB
        $session->update(['host_score' => 0, 'guest_score' => 0]);
        $scores = $service->getScores($session->fresh());

        expect($scores)->toBe(['host' => 0, 'guest' => 0]);
    });

    it('syncs scores to database', function (): void {
        Event::fake([DuelScoreUpdated::class]);

        $session = LiveSession::factory()->active()->create([
            'host_score' => 0,
            'guest_score' => 0,
        ]);
        $service = app(DuelScoreService::class);

        $service->initializeSession($session);
        $service->addPoints($session, 'host', 100);

        // Refresh from DB to verify sync
        $session->refresh();

        expect($session->host_score)->toBe(100);
        expect($session->guest_score)->toBe(0);
    });

    it('determines leader correctly', function (): void {
        $session = LiveSession::factory()->create();
        $service = app(DuelScoreService::class);

        $service->setScores($session, 100, 50);
        expect($service->getLeader($session))->toBe('host');

        $service->setScores($session, 30, 80);
        expect($service->getLeader($session))->toBe('guest');

        $service->setScores($session, 50, 50);
        expect($service->getLeader($session))->toBeNull();
    });

    it('detects tied scores', function (): void {
        $session = LiveSession::factory()->create();
        $service = app(DuelScoreService::class);

        $service->setScores($session, 50, 50);
        expect($service->isTied($session))->toBeTrue();

        $service->setScores($session, 51, 50);
        expect($service->isTied($session))->toBeFalse();
    });

    it('calculates score difference', function (): void {
        $session = LiveSession::factory()->create();
        $service = app(DuelScoreService::class);

        $service->setScores($session, 100, 70);
        expect($service->getScoreDifference($session))->toBe(30);

        $service->setScores($session, 50, 80);
        expect($service->getScoreDifference($session))->toBe(30);

        $service->setScores($session, 50, 50);
        expect($service->getScoreDifference($session))->toBe(0);
    });

    it('loads from database on cache miss', function (): void {
        $session = LiveSession::factory()->create([
            'host_score' => 150,
            'guest_score' => 120,
        ]);

        // Don't initialize cache - simulate cache miss
        $service = app(DuelScoreService::class);
        $scores = $service->getScores($session);

        expect($scores)->toBe(['host' => 150, 'guest' => 120]);
    });
});
