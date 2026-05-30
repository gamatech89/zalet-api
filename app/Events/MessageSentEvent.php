<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSentEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->message->conversation_id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $this->message->load(['sender:id,username', 'reactions.user:id,username', 'repliedTo.sender:id,username']);

        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender' => [
                'id' => $this->message->sender->id,
                'username' => $this->message->sender->username,
            ],
            'content' => $this->message->content,
            'message_type' => $this->message->message_type ?? 'text',
            'media_url' => $this->message->media_url,
            'reactions' => [],
            'reply_to' => $this->message->repliedTo ? [
                'id' => $this->message->repliedTo->id,
                'content' => $this->message->repliedTo->content,
                'message_type' => $this->message->repliedTo->message_type ?? 'text',
                'sender' => [
                    'id' => $this->message->repliedTo->sender->id,
                    'username' => $this->message->repliedTo->sender->username,
                ],
            ] : null,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
