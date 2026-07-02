<?php

namespace App\Events;

use App\Models\Gift;
use App\Models\StreamSession;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GiftSentEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $sender,
        public User $streamer,
        public Gift $gift,
        public StreamSession $session
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('stream-chat.' . $this->session->live_stream_id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'sender' => [
                'id' => $this->sender->id,
                'username' => $this->sender->username,
            ],
            'gift' => [
                'id' => $this->gift->id,
                'name' => $this->gift->name,
                'coin_price' => (float) $this->gift->coin_price,
                'description' => $this->gift->description,
                'icon_url' => $this->gift->icon_url,
                'icon_2d' => $this->gift->icon_2d,
                'icon_3d' => $this->gift->icon_3d,
            ],
            'session_total' => (float) $this->session->fresh()->total_coins_collected,
            'sender_session_total' => (float) \App\Models\Transaction::query()
                ->where('stream_session_id', $this->session->id)
                ->where('type', 'tip')
                ->whereNotNull('gift_id')
                ->where('from_wallet_id', $this->sender->wallet?->id)
                ->sum('amount'),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'gift.sent';
    }
}
