<?php

declare(strict_types=1);

namespace App\Domains\Duel\Models;

use App\Domains\Identity\Models\User;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a user's participation in a direct message conversation.
 *
 * @property int $id
 * @property int $chat_room_id
 * @property int $user_id
 * @property \Carbon\Carbon|null $last_read_at
 * @property bool $is_muted
 * @property bool $is_blocked
 * @property \Carbon\Carbon $created_at
 * @property-read ChatRoom $chatRoom
 * @property-read User $user
 */
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ConversationFactory
    {
        return ConversationFactory::new();
    }

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'chat_room_id',
        'user_id',
        'last_read_at',
        'is_muted',
        'is_blocked',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
            'is_muted' => 'boolean',
            'is_blocked' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the chat room for this conversation.
     *
     * @return BelongsTo<ChatRoom, $this>
     */
    public function chatRoom(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class);
    }

    /**
     * Get the user in this conversation.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark conversation as read up to now.
     */
    public function markAsRead(): void
    {
        $this->update(['last_read_at' => now()]);
    }

    /**
     * Toggle mute status.
     */
    public function toggleMute(): void
    {
        $this->update(['is_muted' => ! $this->is_muted]);
    }

    /**
     * Block/unblock the conversation.
     */
    public function setBlocked(bool $blocked): void
    {
        $this->update(['is_blocked' => $blocked]);
    }

    /**
     * Check if there are unread messages.
     */
    public function hasUnread(): bool
    {
        if ($this->last_read_at === null) {
            return $this->chatRoom->messages()->exists();
        }

        return $this->chatRoom->messages()
            ->where('created_at', '>', $this->last_read_at)
            ->where('user_id', '!=', $this->user_id)
            ->exists();
    }

    /**
     * Get count of unread messages.
     */
    public function unreadCount(): int
    {
        if ($this->last_read_at === null) {
            return $this->chatRoom->messages()
                ->where('user_id', '!=', $this->user_id)
                ->count();
        }

        return $this->chatRoom->messages()
            ->where('created_at', '>', $this->last_read_at)
            ->where('user_id', '!=', $this->user_id)
            ->count();
    }
}
