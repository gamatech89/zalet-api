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
 * Event broadcast when a user joins a duel as viewer or participant.
 */
final class UserJoinedDuel implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly LiveSession $session,
        public readonly User $user,
        public readonly string $role
    ) {}

    /**
     * @return array<Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('duel.' . $this->session->uuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.joined';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session_uuid' => $this->session->uuid,
            'user' => [
                'id' => $this->user->id,
                'uuid' => $this->user->uuid,
                'username' => $this->user->profile?->username,
                'avatar_url' => $this->user->profile?->avatar_url,
            ],
            'role' => $this->role,
            'joined_at' => now()->toIso8601String(),
        ];
    }
}
