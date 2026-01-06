<?php

declare(strict_types=1);

namespace App\Domains\Duel\Resources;

use App\Domains\Duel\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Conversation
 */
final class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Domains\Identity\Models\User|null $currentUser */
        $currentUser = $request->user();

        /** @var \App\Domains\Identity\Models\User|null $otherUser */
        $otherUser = null;
        if ($this->relationLoaded('chatRoom') && $currentUser !== null) {
            $otherUser = $this->chatRoom->getOtherParticipant($currentUser);
        }

        /** @var \App\Domains\Duel\Models\Message|null $latestMessage */
        $latestMessage = null;
        if ($this->relationLoaded('chatRoom') && $this->chatRoom->relationLoaded('messages')) {
            $latestMessage = $this->chatRoom->messages->first();
        }

        return [
            'id' => $this->id,
            'roomUuid' => $this->chatRoom->uuid,
            'otherUser' => $otherUser !== null ? [
                'uuid' => $otherUser->uuid,
                'username' => $otherUser->profile?->username,
                'displayName' => $otherUser->profile?->display_name,
                'avatarUrl' => $otherUser->profile?->avatar_url,
            ] : null,
            'latestMessage' => $latestMessage !== null ? [
                'content' => $latestMessage->content,
                'createdAt' => $latestMessage->created_at->toIso8601String(),
                'isFromMe' => $currentUser !== null && $latestMessage->user_id === $currentUser->id,
            ] : null,
            'unreadCount' => $this->unreadCount(),
            'isMuted' => $this->is_muted,
            'isBlocked' => $this->is_blocked,
            'lastReadAt' => $this->last_read_at?->toIso8601String(),
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
