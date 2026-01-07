<?php

namespace App\Services;

use App\Models\Bar;
use App\Models\BarMessage;
use App\Models\BarMessageReaction;
use App\Domains\Identity\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class BarMessageService
{
    public function __construct(
        private LevelService $levelService
    ) {}

    /**
     * Send a message to bar
     */
    public function sendMessage(User $user, Bar $bar, string $content, ?int $replyToId = null): BarMessage
    {
        // Check if user is member
        $member = $bar->members()->where('user_id', $user->id)->first();
        
        if (!$member) {
            throw new \Exception('Not a member of this bar.');
        }

        // Check if muted
        if ($member->isMuted()) {
            throw new \Exception('You are muted until ' . $member->muted_until->format('H:i'));
        }

        // Validate reply_to if provided
        if ($replyToId) {
            $replyTo = BarMessage::where('id', $replyToId)
                ->where('bar_id', $bar->id)
                ->first();
            
            if (!$replyTo) {
                throw new \Exception('Reply message not found.');
            }
        }

        $message = BarMessage::create([
            'bar_id' => $bar->id,
            'user_id' => $user->id,
            'content' => $content,
            'reply_to_id' => $replyToId,
        ]);

        // Award XP
        $this->levelService->awardBarMessageXp($user);

        return $message->load(['user.profile', 'replyTo.user.profile']);
    }

    /**
     * Delete a message
     */
    public function deleteMessage(User $user, BarMessage $message): bool
    {
        $bar = $message->bar;

        // User can delete their own message or moderator/owner can delete any
        if ($message->user_id !== $user->id && !$bar->isModeratorOrOwner($user)) {
            throw new \Exception('Not authorized to delete this message.');
        }

        $message->softDelete();
        return true;
    }

    /**
     * Add reaction to message
     */
    public function addReaction(User $user, BarMessage $message, string $emoji): BarMessageReaction
    {
        $bar = $message->bar;

        // Check if user is member
        if (!$bar->hasMember($user)) {
            throw new \Exception('Not a member of this bar.');
        }

        // Check if already reacted with this emoji
        $existing = BarMessageReaction::where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            return $existing;
        }

        $reaction = BarMessageReaction::create([
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => $emoji,
        ]);

        // Award XP
        $this->levelService->awardBarReactionXp($user);

        return $reaction;
    }

    /**
     * Remove reaction from message
     */
    public function removeReaction(User $user, BarMessage $message, string $emoji): bool
    {
        $deleted = BarMessageReaction::where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->where('emoji', $emoji)
            ->delete();

        return $deleted > 0;
    }

    /**
     * Get messages for bar (paginated, newest first)
     */
    public function getMessages(Bar $bar, int $perPage = 50, ?int $beforeId = null): LengthAwarePaginator
    {
        $query = BarMessage::where('bar_id', $bar->id)
            ->with([
                'user.profile',
                'replyTo:id,content,user_id',
                'replyTo.user.profile',
                'reactions',
            ])
            ->orderByDesc('created_at');

        if ($beforeId) {
            $query->where('id', '<', $beforeId);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get messages since a specific message ID (for real-time sync)
     */
    public function getMessagesSince(Bar $bar, int $sinceId): \Illuminate\Database\Eloquent\Collection
    {
        return BarMessage::where('bar_id', $bar->id)
            ->where('id', '>', $sinceId)
            ->with([
                'user.profile',
                'replyTo:id,content,user_id',
                'replyTo.user.profile',
                'reactions',
            ])
            ->orderBy('created_at')
            ->get();
    }
}
