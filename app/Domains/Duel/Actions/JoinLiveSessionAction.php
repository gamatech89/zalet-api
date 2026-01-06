<?php

declare(strict_types=1);

namespace App\Domains\Duel\Actions;

use App\Domains\Duel\Enums\DuelEventType;
use App\Domains\Duel\Enums\LiveSessionStatus;
use App\Domains\Duel\Events\DuelStarted;
use App\Domains\Duel\Events\UserJoinedDuel;
use App\Domains\Duel\Models\DuelEvent;
use App\Domains\Duel\Models\LiveSession;
use App\Domains\Identity\Models\User;

/**
 * Action to join a live duel session as guest.
 */
final class JoinLiveSessionAction
{
    /**
     * Join a live session as the guest.
     *
     * @throws \InvalidArgumentException
     */
    public function execute(LiveSession $session, User $guest): LiveSession
    {
        // Validate session can accept guest
        if ($session->status !== LiveSessionStatus::WAITING) {
            throw new \InvalidArgumentException('Session is not waiting for a guest.');
        }

        if ($session->guest_id !== null) {
            throw new \InvalidArgumentException('Session already has a guest.');
        }

        if ($session->host_id === $guest->id) {
            throw new \InvalidArgumentException('Host cannot join as guest.');
        }

        // Update session with guest and start
        $session->update([
            'guest_id' => $guest->id,
            'status' => LiveSessionStatus::ACTIVE,
            'started_at' => now(),
        ]);

        // Record event
        DuelEvent::create([
            'live_session_id' => $session->id,
            'actor_id' => $guest->id,
            'event_type' => DuelEventType::USER_JOINED,
            'payload' => [
                'role' => 'guest',
            ],
        ]);

        // Refresh to get updated relationships
        $session->refresh();

        // Broadcast events
        UserJoinedDuel::dispatch($session, $guest, 'guest');
        DuelStarted::dispatch($session);

        return $session;
    }
}
