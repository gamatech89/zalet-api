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
 * Event broadcast when a duel session ends.
 */
final class DuelEnded implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly LiveSession $session
    ) {}

    /**
     * @return array<Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PresenceChannel('duel.' . $this->session->uuid),
            new Channel('duel.' . $this->session->uuid . '.scores'),
        ];

        if ($this->session->chatRoom !== null) {
            $channels[] = new PresenceChannel('chat.' . $this->session->chatRoom->uuid);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'duel.ended';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session_uuid' => $this->session->uuid,
            'status' => $this->session->status->value,
            'host' => $this->formatUser($this->session->host),
            'guest' => $this->session->guest ? $this->formatUser($this->session->guest) : null,
            'final_scores' => [
                'host' => $this->session->host_score,
                'guest' => $this->session->guest_score,
            ],
            'winner' => $this->session->winner ? $this->formatUser($this->session->winner) : null,
            'started_at' => $this->session->started_at?->toIso8601String(),
            'ended_at' => $this->session->ended_at?->toIso8601String(),
            'duration_seconds' => $this->session->started_at && $this->session->ended_at
                ? $this->session->started_at->diffInSeconds($this->session->ended_at)
                : null,
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
            'avatar_url' => $user->profile?->avatar_url,
        ];
    }
}
