<?php

declare(strict_types=1);

namespace App\Domains\Duel\Events;

use App\Domains\Duel\Models\LiveSession;
use App\Domains\Identity\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when duel scores are updated.
 */
final class DuelScoreUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly LiveSession $session,
        public readonly string $scoringParty,
        public readonly int $pointsAdded
    ) {}

    /**
     * @return array<Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('duel.' . $this->session->uuid),
            new Channel('duel.' . $this->session->uuid . '.scores'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'duel.score.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session_uuid' => $this->session->uuid,
            'scores' => [
                'host' => $this->session->host_score,
                'guest' => $this->session->guest_score,
            ],
            'scoring_party' => $this->scoringParty,
            'points_added' => $this->pointsAdded,
            'host' => $this->formatUser($this->session->host),
            'guest' => $this->session->guest ? $this->formatUser($this->session->guest) : null,
            'leader' => $this->determineLeader(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'username' => $user->profile?->username,
        ];
    }

    private function determineLeader(): ?string
    {
        if ($this->session->host_score > $this->session->guest_score) {
            return 'host';
        }

        if ($this->session->guest_score > $this->session->host_score) {
            return 'guest';
        }

        return null; // Tied
    }
}
