<?php

declare(strict_types=1);

namespace App\Domains\Duel\Events;

use App\Domains\Duel\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when a new message is sent to a chat room.
 */
final class MessageSent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Message $message
    ) {}

    /**
     * @return array<Channel>
     */
    public function broadcastOn(): array
    {
        $chatRoom = $this->message->chatRoom;

        return [
            new PresenceChannel('chat.' . $chatRoom->uuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'uuid' => $this->message->uuid,
            'type' => $this->message->type->value,
            'content' => $this->message->content,
            'user' => $this->message->user ? [
                'id' => $this->message->user->id,
                'uuid' => $this->message->user->uuid,
                'username' => $this->message->user->profile?->username,
                'avatar_url' => $this->message->user->profile?->avatar_url,
            ] : null,
            'meta' => $this->message->meta,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}
