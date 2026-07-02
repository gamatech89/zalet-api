<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamEndedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $streamId,
        public ?int $durationMinutes,
        public int $peakViewers,
        public float $totalCoins,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('stream-chat.' . $this->streamId)];
    }

    public function broadcastWith(): array
    {
        return [
            'stream_id'             => $this->streamId,
            'duration_minutes'      => $this->durationMinutes,
            'peak_viewers'          => $this->peakViewers,
            'total_coins_collected' => $this->totalCoins,
        ];
    }

    public function broadcastAs(): string
    {
        return 'stream.ended';
    }
}
