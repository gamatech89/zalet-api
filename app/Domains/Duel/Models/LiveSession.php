<?php

declare(strict_types=1);

namespace App\Domains\Duel\Models;

use App\Domains\Duel\Enums\LiveSessionStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\HasUuid;
use Database\Factories\LiveSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property int $host_id
 * @property int|null $guest_id
 * @property int|null $chat_room_id
 * @property LiveSessionStatus $status
 * @property int $host_score
 * @property int $guest_score
 * @property int|null $winner_id
 * @property \Carbon\Carbon|null $scheduled_at
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $ended_at
 * @property int|null $duration_seconds
 * @property array<string, mixed> $meta
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read User $host
 * @property-read User|null $guest
 * @property-read User|null $winner
 * @property-read ChatRoom|null $chatRoom
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DuelEvent> $events
 */
class LiveSession extends Model
{
    /** @use HasFactory<LiveSessionFactory> */
    use HasFactory;
    use HasUuid;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): LiveSessionFactory
    {
        return LiveSessionFactory::new();
    }

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'host_id',
        'guest_id',
        'chat_room_id',
        'status',
        'host_score',
        'guest_score',
        'winner_id',
        'scheduled_at',
        'started_at',
        'ended_at',
        'duration_seconds',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LiveSessionStatus::class,
            'host_score' => 'integer',
            'guest_score' => 'integer',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
            'meta' => 'array',
        ];
    }

    /**
     * Get the host user.
     *
     * @return BelongsTo<User, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    /**
     * Get the guest user.
     *
     * @return BelongsTo<User, $this>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_id');
    }

    /**
     * Get the winner user.
     *
     * @return BelongsTo<User, $this>
     */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
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
     * Get all events for this session.
     *
     * @return HasMany<DuelEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(DuelEvent::class)->orderByDesc('created_at');
    }

    /**
     * Check if user can join this session.
     */
    public function canUserJoin(User $user): bool
    {
        // Host and guest can always join
        if ($user->id === $this->host_id || $user->id === $this->guest_id) {
            return true;
        }

        // Others can join as spectators if session is active
        return $this->status->isInProgress();
    }

    /**
     * Check if session is waiting for guest.
     */
    public function isWaitingForGuest(): bool
    {
        return $this->status === LiveSessionStatus::WAITING && $this->guest_id === null;
    }

    /**
     * Check if session is active (live).
     */
    public function isActive(): bool
    {
        return $this->status === LiveSessionStatus::ACTIVE;
    }

    /**
     * Check if session is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === LiveSessionStatus::COMPLETED;
    }

    /**
     * Check if session has ended (completed or cancelled).
     */
    public function isEnded(): bool
    {
        return in_array($this->status, [
            LiveSessionStatus::COMPLETED,
            LiveSessionStatus::CANCELLED,
        ], true);
    }

    /**
     * Get the target for scoring (host or guest based on recipient).
     */
    public function getScoreTarget(User $recipient): string
    {
        return $recipient->id === $this->host_id ? 'host' : 'guest';
    }

    /**
     * Add score to the appropriate participant.
     */
    public function addScore(User $recipient, int $credits): void
    {
        if ($recipient->id === $this->host_id) {
            $this->increment('host_score', $credits);
        } else {
            $this->increment('guest_score', $credits);
        }
    }

    /**
     * Determine and set the winner based on scores.
     */
    public function determineWinner(): void
    {
        if ($this->host_score > $this->guest_score) {
            $this->winner_id = $this->host_id;
        } elseif ($this->guest_score > $this->host_score) {
            $this->winner_id = $this->guest_id;
        }
        // If tied, winner_id remains null (draw)

        $this->save();
    }

    /**
     * Get the broadcast channel name.
     */
    public function broadcastChannelName(): string
    {
        return "duel.{$this->uuid}";
    }
}
