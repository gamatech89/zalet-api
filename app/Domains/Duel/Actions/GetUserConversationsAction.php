<?php

declare(strict_types=1);

namespace App\Domains\Duel\Actions;

use App\Domains\Duel\Models\ChatRoom;
use App\Domains\Identity\Models\User;

/**
 * Get all conversations (DMs) for a user.
 */
final class GetUserConversationsAction
{
    /**
     * Get all DM conversations for a user, with latest message and unread counts.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Domains\Duel\Models\Conversation>
     */
    public function execute(User $user, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return $user->conversations()
            ->with([
                'chatRoom.messages' => fn ($q) => $q->latest()->limit(1),
                'chatRoom.conversations.user.profile',
            ])
            ->whereHas('chatRoom', fn ($q) => $q->where('is_active', true))
            ->where('is_blocked', false)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
