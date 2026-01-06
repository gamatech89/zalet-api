<?php

declare(strict_types=1);

namespace App\Domains\Duel\Models;

use App\Domains\Duel\Enums\DuelEventType;
use App\Domains\Identity\Models\User;
use Database\Factories\DuelEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $live_session_id
 * @property DuelEventType $event_type
 * @property int|null $actor_id
 * @property int|null $target_id
 * @property array<string, mixed> $payload
 * @property \Carbon\Carbon $created_at
 * @property-read LiveSession $liveSession
 * @property-read User|null $actor
 * @property-read User|null $target
 */
class DuelEvent extends Model
{
    /** @use HasFactory<DuelEventFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): DuelEventFactory
    {
        return DuelEventFactory::new();
    }

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'live_session_id',
        'event_type',
        'actor_id',
        'target_id',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => DuelEventType::class,
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (DuelEvent $event): void {
            $event->created_at = now();
        });
    }

    /**
     * Get the live session.
     *
     * @return BelongsTo<LiveSession, $this>
     */
    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

    /**
     * Get the actor (user who triggered the event).
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Get the target (user receiving the action).
     *
     * @return BelongsTo<User, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_id');
    }

    /**
     * Check if this is a gift event.
     */
    public function isGift(): bool
    {
        return $this->event_type === DuelEventType::GIFT_SENT;
    }
}
