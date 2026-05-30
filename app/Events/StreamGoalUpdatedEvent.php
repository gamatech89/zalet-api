<?php

namespace App\Events;

use App\Models\LiveStream;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamGoalUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public LiveStream $stream,
        public int $goalIndex,
        public bool $completed,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('stream-chat.' . $this->stream->id),
        ];
    }

    public function broadcastWith(): array
    {
        $goal = $this->stream->goals[$this->goalIndex] ?? [];
        return [
            'goal_index'    => $this->goalIndex,
            'goal'          => $goal,
            'completed'     => $this->completed,
            'all_goals'     => $this->stream->goals ?? [],
        ];
    }

    public function broadcastAs(): string
    {
        return 'stream.goal.updated';
    }
}
