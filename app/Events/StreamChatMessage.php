<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamChatMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $streamId,
        public User $sender,
        public string $message
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('stream-chat.' . $this->streamId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => uniqid('msg-'),
            'user' => [
                'id' => $this->sender->id,
                'username' => $this->sender->username,
            ],
            'message' => $this->message,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.message';
    }
}
