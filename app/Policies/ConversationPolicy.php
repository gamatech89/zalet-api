<?php

namespace App\Policies;

use App\Models\Conversation;
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

    private function getMemberRole(User $user, Conversation $conversation): ?string
    {
        return $conversation->users()
            ->where('users.id', $user->id)
            ->first()?->pivot?->role;
    }
}
