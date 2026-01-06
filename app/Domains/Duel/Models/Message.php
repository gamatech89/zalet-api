<?php

declare(strict_types=1);

namespace App\Domains\Duel\Models;

use App\Domains\Duel\Enums\MessageType;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\HasUuid;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $chat_room_id
 * @property int|null $user_id
 * @property string $content
 * @property MessageType $type
 * @property array<string, mixed> $meta
 * @property \Carbon\Carbon $created_at
 * @property-read ChatRoom $chatRoom
 * @property-read User|null $user
 */
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;
    use HasUuid;

    public $timestamps = false;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): MessageFactory
    {
        return MessageFactory::new();
    }

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'chat_room_id',
        'user_id',
        'content',
        'type',
        'meta',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Message $message): void {
            if (! isset($message->attributes['created_at'])) {
                $message->created_at = now();
            }
        });
    }

    /**
     * Get the chat room.
     *
     * @return BelongsTo<ChatRoom, $this>
     */
    public function chatRoom(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class);
    }

    /**
     * Get the user who sent the message.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this is a system message.
     */
    public function isSystem(): bool
    {
        return $this->type === MessageType::SYSTEM;
    }

    /**
     * Check if this is a gift notification message.
     */
    public function isGift(): bool
    {
        return $this->type === MessageType::GIFT;
    }
}
