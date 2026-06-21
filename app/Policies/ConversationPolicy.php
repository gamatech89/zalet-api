<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        if ($user->role === 'admin' && $conversation->is_group) return true;
        return $conversation->users()->where('users.id', $user->id)->exists();
    }

    public function sendMessage(User $user, Conversation $conversation): bool
    {
        if ($user->role === 'admin' && $conversation->is_group) return true;
        return $conversation->users()->where('users.id', $user->id)->exists();
    }

    public function addMembers(User $user, Conversation $conversation): bool
    {
        if (!$conversation->is_group) {
            return false;
        }

        return $conversation->users()->where('users.id', $user->id)->exists();
    }

    public function update(User $user, Conversation $conversation): bool
    {
        if (!$conversation->is_group) {
            return false;
        }

        return in_array($this->getMemberRole($user, $conversation), ['owner', 'admin']);
    }

    public function kickMember(User $user, Conversation $conversation): bool
    {
        if (!$conversation->is_group) {
            return false;
        }

        return in_array($this->getMemberRole($user, $conversation), ['owner', 'admin']);
    }

    public function banMember(User $user, Conversation $conversation): bool
    {
        if (!$conversation->is_group) {
            return false;
        }

        return in_array($this->getMemberRole($user, $conversation), ['owner', 'admin']);
    }

    public function updateMemberRole(User $user, Conversation $conversation): bool
    {
        if (!$conversation->is_group) {
            return false;
        }

        return $this->getMemberRole($user, $conversation) === 'owner';
    }

    public function deleteMessage(User $user, Conversation $conversation, Message $message): bool
    {
        // Sender can always delete their own message
        if ($message->sender_id === $user->id) return true;

        // Platform admin can delete in any group
        if ($user->role === 'admin' && $conversation->is_group) return true;

        // Group owner/admin can delete any message
        if ($conversation->is_group) {
            return in_array($this->getMemberRole($user, $conversation), ['owner', 'admin']);
        }

        return false;
    }

    public function deleteUserMessages(User $user, Conversation $conversation): bool
    {
        if (!$conversation->is_group) return false;
        if ($user->role === 'admin') return true;
        return in_array($this->getMemberRole($user, $conversation), ['owner', 'admin']);
    }

    private function getMemberRole(User $user, Conversation $conversation): ?string
    {
        return $conversation->users()
            ->where('users.id', $user->id)
            ->first()?->pivot?->role;
    }
}
