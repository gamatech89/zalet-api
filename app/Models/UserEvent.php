<?php

namespace App\Models;

use App\Enums\EventType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'event_type',
        'value',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type'  => EventType::class,
            'value'       => 'decimal:2',
            'metadata'    => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}