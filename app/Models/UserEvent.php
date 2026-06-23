<?php

namespace App\Models;

use App\Enums\EventType;
use App\Observers\UserEventObserver;
use App\Services\Achievements\Payloads\EventPayload;
use App\Services\Achievements\Payloads\EventPayloadCast;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(UserEventObserver::class)]
class UserEvent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'data' => EventPayloadCast::class,
        ];
    }

    public static function record(User $user, EventType $eventType, EventPayload $payload): static
    {
        return static::create([
            'user_id' => $user->id,
            'type' => $eventType->value,
            'data' => $payload,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
