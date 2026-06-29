<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ViewerLeftEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $streamId,
        public string $username,
        public int $currentViewers
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
            'username' => $this->username,
            'current_viewers' => $this->currentViewers,
        ];
    }

    public function broadcastAs(): string
    {
        return 'viewer.left';
    }
}
