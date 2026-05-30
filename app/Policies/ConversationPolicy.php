<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Determine whether the user can view the conversation.
     * Only participants can view.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->users()->where('users.id', $user->id)->exists();
    }

    /**
     * Determine whether the user can send messages in the conversation.
     * Only participants can send messages.
     */
    public function sendMessage(User $user, Conversation $conversation): bool
    {
        return $conversation->users()->where('users.id', $user->id)->exists();
    }

    /**
     * Determine whether the user can add members to the conversation.
     * Only participants can add members to groups.
     */
    public function addMembers(User $user, Conversation $conversation): bool
    {
        if (!$conversation->is_group) {
            return false;
        }

        return $conversation->users()->where('users.id', $user->id)->exists();
    }
}
