<?php

declare(strict_types=1);

namespace App\Domains\Duel\Events;

use App\Domains\Duel\Models\LiveSession;
use App\Domains\Identity\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when a gift is sent during a duel.
 */
final class DuelGiftSent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param array<string, mixed> $giftData
     */
    public function __construct(
        public readonly LiveSession $session,
        public readonly User $sender,
        public readonly User $recipient,
        public readonly array $giftData
    ) {}

    /**
     * @return array<Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PresenceChannel('duel.' . $this->session->uuid),
            new Channel('duel.' . $this->session->uuid . '.scores'),
        ];

        if ($this->session->chatRoom !== null) {
            $channels[] = new PresenceChannel('chat.' . $this->session->chatRoom->uuid);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'duel.gift.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $recipientRole = $this->determineRecipientRole();

        return [
            'session_uuid' => $this->session->uuid,
            'gift' => [
                'slug' => $this->giftData['slug'],
                'name' => $this->giftData['name'],
                'emoji' => $this->giftData['emoji'],
                'credit_value' => $this->giftData['credit_value'],
            ],
            'sender' => [
                'id' => $this->sender->id,
                'uuid' => $this->sender->uuid,
                'username' => $this->sender->profile?->username,
                'avatar_url' => $this->sender->profile?->avatar_url,
            ],
            'recipient' => [
                'id' => $this->recipient->id,
                'uuid' => $this->recipient->uuid,
                'username' => $this->recipient->profile?->username,
                'avatar_url' => $this->recipient->profile?->avatar_url,
                'role' => $recipientRole,
            ],
            'scores' => [
                'host' => $this->session->host_score,
                'guest' => $this->session->guest_score,
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    private function determineRecipientRole(): string
    {
        if ($this->session->host_id === $this->recipient->id) {
            return 'host';
        }

        if ($this->session->guest_id === $this->recipient->id) {
            return 'guest';
        }

        return 'viewer';
    }
}
