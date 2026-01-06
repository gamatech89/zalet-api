<?php

declare(strict_types=1);

namespace App\Domains\Duel\Models;

use App\Domains\Duel\Enums\ChatRoomType;
use App\Domains\Identity\Models\Location;
use App\Domains\Shared\Traits\HasUuid;
use App\Domains\Identity\Models\User;
use Database\Factories\ChatRoomFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $description
 * @property string $slug
 * @property ChatRoomType $type
 * @property int|null $location_id
 * @property int|null $creator_id
 * @property int $max_participants
 * @property bool $is_active
 * @property array<string, mixed> $meta
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Location|null $location
 * @property-read User|null $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Message> $messages
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Conversation> $conversations
 * @property-read LiveSession|null $liveSession
 */
class ChatRoom extends Model
{
    /** @use HasFactory<ChatRoomFactory> */
    use HasFactory;
    use HasUuid;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ChatRoomFactory
    {
        return ChatRoomFactory::new();
    }

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'description',
        'slug',
        'type',
        'location_id',
        'creator_id',
        'max_participants',
        'is_active',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ChatRoomType::class,
            'max_participants' => 'integer',
            'is_active' => 'boolean',
            'meta' => 'array',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ChatRoom $room): void {
            if (empty($room->slug)) {
                $room->slug = Str::slug($room->name) . '-' . Str::random(6);
            }
        });
    }

    /**
     * Get the location this room belongs to.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the user who created this room.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Get all messages in this room.
     *
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderByDesc('created_at');
    }

    /**
     * Get conversation participants (for DM rooms).
     *
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Get the live session associated with this room (for duel type).
     *
     * @return HasOne<LiveSession, $this>
     */
    public function liveSession(): HasOne
    {
        return $this->hasOne(LiveSession::class);
    }

    /**
     * Get all live sessions for this room.
     *
     * @return HasMany<LiveSession, $this>
     */
    public function liveSessions(): HasMany
    {
        return $this->hasMany(LiveSession::class);
    }

    /**
     * Check if room is a public kafana.
     */
    public function isKafana(): bool
    {
        return $this->type === ChatRoomType::PUBLIC_KAFANA;
    }

    /**
     * Check if room is a duel arena.
     */
    public function isDuel(): bool
    {
        return $this->type === ChatRoomType::DUEL;
    }

    /**
     * Check if room is a direct message conversation.
     */
    public function isDirectMessage(): bool
    {
        return $this->type === ChatRoomType::DIRECT_MESSAGE;
    }

    /**
     * Get the other participant in a DM conversation.
     */
    public function getOtherParticipant(User $user): ?User
    {
        if (! $this->isDirectMessage()) {
            return null;
        }

        return $this->conversations()
            ->where('user_id', '!=', $user->id)
            ->with('user')
            ->first()
            ?->user;
    }

    /**
     * Check if a user is a participant in this room (for DMs).
     */
    public function hasParticipant(User $user): bool
    {
        if ($this->isDirectMessage()) {
            return $this->conversations()->where('user_id', $user->id)->exists();
        }

        return true; // Public rooms are open to all
    }

    /**
     * Get the broadcast channel name for this room.
     */
    public function broadcastChannelName(): string
    {
        return match ($this->type) {
            ChatRoomType::DUEL => "duel.{$this->uuid}",
            ChatRoomType::DIRECT_MESSAGE => "dm.{$this->uuid}",
            default => "kafana.{$this->uuid}",
        };
    }
}
