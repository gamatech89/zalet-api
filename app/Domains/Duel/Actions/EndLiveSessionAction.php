<?php

declare(strict_types=1);

namespace App\Domains\Duel\Actions;

use App\Domains\Duel\Enums\DuelEventType;
use App\Domains\Duel\Enums\LiveSessionStatus;
use App\Domains\Duel\Events\DuelEnded;
use App\Domains\Duel\Models\DuelEvent;
use App\Domains\Duel\Models\LiveSession;
use App\Domains\Duel\Services\DuelScoreService;
use App\Domains\Identity\Models\User;

/**
 * Action to end a live duel session.
 */
final readonly class EndLiveSessionAction
{
    public function __construct(
        private DuelScoreService $scoreService
    ) {}

    /**
     * End a live session and determine the winner.
     *
     * @throws \InvalidArgumentException
     */
    public function execute(LiveSession $session, ?User $endedBy = null): LiveSession
    {
        // Validate session can be ended
        if (in_array($session->status, [LiveSessionStatus::COMPLETED, LiveSessionStatus::CANCELLED], true)) {
            throw new \InvalidArgumentException('Session is already ended.');
        }

        // Get final scores from Redis
        $scores = $this->scoreService->getScores($session);

        // Determine winner
        $winnerId = null;
        if ($session->guest_id !== null) {
            if ($scores['host'] > $scores['guest']) {
                $winnerId = $session->host_id;
            } elseif ($scores['guest'] > $scores['host']) {
                $winnerId = $session->guest_id;
            }
            // null if tied
        }

        // Update session
        $status = $session->status === LiveSessionStatus::WAITING
            ? LiveSessionStatus::CANCELLED
            : LiveSessionStatus::COMPLETED;

        $session->update([
            'status' => $status,
            'host_score' => $scores['host'],
            'guest_score' => $scores['guest'],
            'winner_id' => $winnerId,
            'ended_at' => now(),
        ]);

        // Record event
        DuelEvent::create([
            'live_session_id' => $session->id,
            'actor_id' => $endedBy?->id,
            'event_type' => DuelEventType::DUEL_ENDED,
            'payload' => [
                'winner_id' => $winnerId,
                'final_scores' => $scores,
                'ended_by' => $endedBy?->id,
            ],
        ]);

        // Clear Redis cache
        $this->scoreService->clearCache($session);

        // Refresh and broadcast
        $session->refresh();
        DuelEnded::dispatch($session);

        return $session;
    }

    /**
     * Cancel a session that hasn't started.
     */
    public function cancel(LiveSession $session, ?User $cancelledBy = null): LiveSession
    {
        if ($session->status !== LiveSessionStatus::WAITING) {
            throw new \InvalidArgumentException('Only waiting sessions can be cancelled.');
        }

        $session->update([
            'status' => LiveSessionStatus::CANCELLED,
            'ended_at' => now(),
        ]);

        // Record event
        DuelEvent::create([
            'live_session_id' => $session->id,
            'actor_id' => $cancelledBy?->id,
            'event_type' => DuelEventType::DUEL_ENDED,
            'payload' => [
                'reason' => 'cancelled',
                'cancelled_by' => $cancelledBy?->id,
            ],
        ]);

        // Clear cache
        $this->scoreService->clearCache($session);

        $session->refresh();
        DuelEnded::dispatch($session);

        return $session;
    }
}
