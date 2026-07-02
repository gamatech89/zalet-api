<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamReactionEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $streamId,
        public string $username,
        public int $count,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('stream-chat.' . $this->streamId)];
    }

    public function broadcastWith(): array
    {
        return ['username' => $this->username, 'count' => $this->count];
    }

    public function broadcastAs(): string
    {
        return 'stream.reaction';
    }
}
