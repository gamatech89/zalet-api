<?php

declare(strict_types=1);

namespace App\Domains\Duel\Actions;

use App\Domains\Duel\Enums\MessageType;
use App\Domains\Duel\Events\MessageSent;
use App\Domains\Duel\Models\ChatRoom;
use App\Domains\Duel\Models\Message;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Str;

/**
 * Action to send a message to a chat room.
 */
final class SendMessageAction
{
    /**
     * Send a text message to a chat room.
     *
     * @param array<string, mixed> $meta
     */
    public function execute(ChatRoom $room, User $user, string $content, array $meta = []): Message
    {
        if (! $room->is_active) {
            throw new \InvalidArgumentException('Cannot send messages to inactive rooms.');
        }

        if (mb_strlen($content) > 1000) {
            throw new \InvalidArgumentException('Message content too long.');
        }

        $message = Message::create([
            'uuid' => (string) Str::uuid(),
            'chat_room_id' => $room->id,
            'user_id' => $user->id,
            'type' => MessageType::TEXT,
            'content' => $content,
            'meta' => $meta,
        ]);

        MessageSent::dispatch($message);

        return $message;
    }

    /**
     * Send a system message to a chat room.
     *
     * @param array<string, mixed> $meta
     */
    public function sendSystemMessage(ChatRoom $room, string $content, array $meta = []): Message
    {
        $message = Message::create([
            'uuid' => (string) Str::uuid(),
            'chat_room_id' => $room->id,
            'user_id' => null,
            'type' => MessageType::SYSTEM,
            'content' => $content,
            'meta' => $meta,
        ]);

        MessageSent::dispatch($message);

        return $message;
    }
}
