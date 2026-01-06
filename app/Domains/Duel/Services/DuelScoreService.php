<?php

declare(strict_types=1);

namespace App\Domains\Duel\Services;

use App\Domains\Duel\Events\DuelScoreUpdated;
use App\Domains\Duel\Models\LiveSession;
use Illuminate\Support\Facades\Redis;

/**
 * Service for managing real-time duel scores using Redis.
 *
 * Provides fast score updates and retrieval with Redis caching,
 * while keeping the database in sync.
 */
final class DuelScoreService
{
    private const SCORE_KEY_PREFIX = 'duel:scores:';
    private const SCORE_TTL = 86400; // 24 hours

    /**
     * Get current scores for a duel session.
     *
     * @return array{host: int, guest: int}
     */
    public function getScores(LiveSession $session): array
    {
        $key = $this->getScoreKey($session);

        $scores = Redis::hgetall($key);

        if (empty($scores)) {
            // Cache miss - load from database and cache
            return $this->cacheScoresFromDatabase($session);
        }

        return [
            'host' => (int) ($scores['host'] ?? 0),
            'guest' => (int) ($scores['guest'] ?? 0),
        ];
    }

    /**
     * Add points to a participant's score.
     */
    public function addPoints(LiveSession $session, string $party, int $points): void
    {
        if (! in_array($party, ['host', 'guest'], true)) {
            throw new \InvalidArgumentException('Party must be "host" or "guest"');
        }

        $key = $this->getScoreKey($session);

        // Increment in Redis
        Redis::hincrby($key, $party, $points);
        Redis::expire($key, self::SCORE_TTL);

        // Get updated scores
        $scores = $this->getScores($session);

        // Update database
        $session->update([
            'host_score' => $scores['host'],
            'guest_score' => $scores['guest'],
        ]);

        // Broadcast score update
        /** @var LiveSession $freshSession */
        $freshSession = $session->fresh();
        DuelScoreUpdated::dispatch($freshSession, $party, $points);
    }

    /**
     * Set scores directly (used for corrections or initialization).
     */
    public function setScores(LiveSession $session, int $hostScore, int $guestScore): void
    {
        $key = $this->getScoreKey($session);

        Redis::hmset($key, [
            'host' => $hostScore,
            'guest' => $guestScore,
        ]);
        Redis::expire($key, self::SCORE_TTL);

        // Update database
        $session->update([
            'host_score' => $hostScore,
            'guest_score' => $guestScore,
        ]);
    }

    /**
     * Reset scores to zero.
     */
    public function resetScores(LiveSession $session): void
    {
        $this->setScores($session, 0, 0);
    }

    /**
     * Clear cached scores (used when session ends).
     */
    public function clearCache(LiveSession $session): void
    {
        $key = $this->getScoreKey($session);
        Redis::del($key);
    }

    /**
     * Initialize scores for a new session.
     */
    public function initializeSession(LiveSession $session): void
    {
        $this->setScores($session, 0, 0);
    }

    /**
     * Get the leader of the duel.
     */
    public function getLeader(LiveSession $session): ?string
    {
        $scores = $this->getScores($session);

        if ($scores['host'] > $scores['guest']) {
            return 'host';
        }

        if ($scores['guest'] > $scores['host']) {
            return 'guest';
        }

        return null; // Tied
    }

    /**
     * Check if scores are tied.
     */
    public function isTied(LiveSession $session): bool
    {
        $scores = $this->getScores($session);

        return $scores['host'] === $scores['guest'];
    }

    /**
     * Get score difference.
     */
    public function getScoreDifference(LiveSession $session): int
    {
        $scores = $this->getScores($session);

        return abs($scores['host'] - $scores['guest']);
    }

    /**
     * Cache scores from database.
     *
     * @return array{host: int, guest: int}
     */
    private function cacheScoresFromDatabase(LiveSession $session): array
    {
        $key = $this->getScoreKey($session);

        $scores = [
            'host' => $session->host_score,
            'guest' => $session->guest_score,
        ];

        Redis::hmset($key, $scores);
        Redis::expire($key, self::SCORE_TTL);

        return $scores;
    }

    /**
     * Get Redis key for session scores.
     */
    private function getScoreKey(LiveSession $session): string
    {
        return self::SCORE_KEY_PREFIX . $session->uuid;
    }
}
