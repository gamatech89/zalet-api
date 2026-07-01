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
        $channels = [
            new PrivateChannel('conversation.' . $this->message->conversation_id),
        ];

        // Also broadcast to each participant's private user channel
        $this->message->loadMissing('conversation.users');
        foreach ($this->message->conversation->users as $user) {
            $channels[] = new PrivateChannel('user.' . $user->id);
        }

        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $this->message->load(['sender:id,username,name,subscription_level,role', 'sender.profile:user_id,avatar_url', 'reactions.user:id,username', 'repliedTo.sender:id,username,name,subscription_level,role']);

        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender' => [
                'id' => $this->message->sender->id,
                'username' => $this->message->sender->username,
                'name' => $this->message->sender->name ?: null,
                'avatar_url' => $this->message->sender->profile?->avatar_url,
                'subscription_level' => $this->message->sender->subscription_level,
                'is_creator' => in_array($this->message->sender->role, ['creator', 'admin']),
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
                    'name' => $this->message->repliedTo->sender->name ?: null,
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
