<?php

declare(strict_types=1);

namespace App\Domains\Duel\Events;

use App\Domains\Duel\Models\ChatRoom;
use App\Domains\Identity\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a user joins a chat room.
 */
final class UserJoinedRoom implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly ChatRoom $room,
        public readonly User $user,
    ) {}

    /**
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel($this->room->broadcastChannelName()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user' => [
                'id' => $this->user->id,
                'uuid' => $this->user->uuid,
                'username' => $this->user->profile?->username,
                'avatarUrl' => $this->user->profile?->avatar_url,
            ],
            'room' => [
                'uuid' => $this->room->uuid,
                'name' => $this->room->name,
            ],
            'joinedAt' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.joined';
    }
}
