<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a gift is sent from one user to another.
 *
 * This event can be used for:
 * - Real-time broadcasting to duel/stream participants
 * - Triggering gift animations in the UI
 * - Analytics and tracking
 */
final class GiftSent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly int $senderId,
        public readonly int $recipientId,
        public readonly string $giftType,
        public readonly int $credits,
        public readonly ?int $liveSessionId = null,
    ) {}

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'senderId' => $this->senderId,
            'recipientId' => $this->recipientId,
            'giftType' => $this->giftType,
            'credits' => $this->credits,
            'liveSessionId' => $this->liveSessionId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
