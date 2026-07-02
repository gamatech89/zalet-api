<?php

namespace App\Services;

use App\Events\StreamEndedEvent;
use App\Events\StreamGoalUpdatedEvent;
use App\Models\LiveStream;
use App\Models\StreamSession;
use App\Models\User;

class LiveStreamService
{
    public function __construct(
        private LiveKitService $liveKit,
    ) {}

    /**
     * Find the user's most recent stream and mark it live.
     * Returns session summary data for the API response.
     */
    public function startStream(User $user): array
    {
        $stream = $user->liveStreams()->latest()->first();

        if (!$stream) {
            abort(404, 'No stream found. Create a stream first.');
        }

        if ($stream->is_live) {
            abort(409, 'Stream is already live.');
        }

        $session = $stream->goLive();

        return [
            'stream_id'  => $stream->id,
            'session_id' => $session->id,
            'stream_mode' => $stream->stream_mode,
            'started_at' => $session->start_time->toIso8601String(),
        ];
    }

    /**
     * Stop the user's active stream, clean up LiveKit, and return summary data.
     */
    public function stopStream(User $user): array
    {
        $stream = $user->liveStreams()->where('is_live', true)->first();

        if (!$stream) {
            abort(404, 'No active stream found.');
        }

        $session = $stream->currentSession;
        $stream->endStream();

        if ($session) {
            $session->refresh();
            broadcast(new StreamEndedEvent(
                $stream->id,
                $session->getDurationMinutes(),
                (int) $session->peak_viewers,
                (float) $session->total_coins_collected,
            ));
        }

        $this->liveKit->deleteRoom($stream);
        $stream->update(['livekit_room_name' => null]);

        return [
            'stream_id'             => $stream->id,
            'session_id'            => $session?->id,
            'duration_minutes'      => $session?->getDurationMinutes(),
            'total_coins_collected' => (float) $session?->total_coins_collected,
            'peak_viewers'          => $session?->peak_viewers,
        ];
    }

    /**
     * Apply coins to incomplete goals in order; overflow rolls into the next goal.
     * Broadcasts StreamGoalUpdatedEvent per affected goal.
     * No-op if the stream has no goals or all are complete.
     */
    public function updateGoalProgress(LiveStream $stream, int $coinAmount): void
    {
        $goals = $stream->goals ?? [];
        if (empty($goals)) {
            return;
        }

        $remaining = $coinAmount;
        $affected  = []; // idx => isNowDone

        foreach ($goals as $idx => $goal) {
            if ($remaining <= 0) {
                break;
            }
            if ($goal['current_coins'] >= $goal['target_coins']) {
                continue;
            }
            $applied = min($goal['target_coins'] - $goal['current_coins'], $remaining);
            $goals[$idx]['current_coins'] += $applied;
            $remaining -= $applied;
            $affected[$idx] = $goals[$idx]['current_coins'] >= $goals[$idx]['target_coins'];
        }

        if (empty($affected)) {
            return;
        }

        $stream->update(['goals' => $goals]);
        $fresh = $stream->fresh();

        foreach ($affected as $idx => $isNowDone) {
            broadcast(new StreamGoalUpdatedEvent($fresh, $idx, $isNowDone));
        }
    }
}
