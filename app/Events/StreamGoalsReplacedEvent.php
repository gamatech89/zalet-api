<?php

namespace App\Events;

use App\Models\LiveStream;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamGoalsReplacedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public LiveStream $stream) {}

    public function broadcastOn(): array
    {
        return [new Channel('stream-chat.' . $this->stream->id)];
    }

    public function broadcastWith(): array
    {
        return ['all_goals' => $this->stream->goals ?? []];
    }

    public function broadcastAs(): string
    {
        return 'stream.goals.replaced';
    }
}
