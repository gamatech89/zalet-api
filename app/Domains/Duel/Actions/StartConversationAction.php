<?php

declare(strict_types=1);

namespace App\Domains\Duel\Actions;

use App\Domains\Duel\Enums\ChatRoomType;
use App\Domains\Duel\Models\ChatRoom;
use App\Domains\Duel\Models\Conversation;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Start or retrieve a direct message conversation between two users.
 */
final class StartConversationAction
{
    /**
     * Start a new conversation or return existing one.
     */
    public function execute(User $initiator, User $recipient): ChatRoom
    {
        // Check if conversation already exists
        $existingRoom = $this->findExistingConversation($initiator, $recipient);

        if ($existingRoom !== null) {
            return $existingRoom;
        }

        // Create new DM room
        return DB::transaction(function () use ($initiator, $recipient): ChatRoom {
            $roomName = $this->generateRoomName($initiator, $recipient);

            $room = ChatRoom::create([
                'uuid' => (string) Str::uuid(),
                'name' => $roomName,
                'type' => ChatRoomType::DIRECT_MESSAGE,
                'creator_id' => $initiator->id,
                'max_participants' => 2,
                'is_active' => true,
                'meta' => [],
            ]);

            // Add both users as participants
            Conversation::create([
                'chat_room_id' => $room->id,
                'user_id' => $initiator->id,
            ]);

            Conversation::create([
                'chat_room_id' => $room->id,
                'user_id' => $recipient->id,
            ]);

            return $room;
        });
    }

    /**
     * Find existing conversation between two users.
     */
    private function findExistingConversation(User $user1, User $user2): ?ChatRoom
    {
        // Find rooms where both users are participants
        return ChatRoom::where('type', ChatRoomType::DIRECT_MESSAGE)
            ->whereHas('conversations', fn ($q) => $q->where('user_id', $user1->id))
            ->whereHas('conversations', fn ($q) => $q->where('user_id', $user2->id))
            ->first();
    }

    /**
     * Generate a room name for the DM.
     */
    private function generateRoomName(User $user1, User $user2): string
    {
        $profile1 = $user1->profile;
        $profile2 = $user2->profile;
        $name1 = $profile1 !== null ? $profile1->username : "User {$user1->id}";
        $name2 = $profile2 !== null ? $profile2->username : "User {$user2->id}";

        return "DM: {$name1} & {$name2}";
    }
}
