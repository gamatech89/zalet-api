<?php

namespace App\Models;

use App\Enums\AggregatorType;
use App\Enums\EventType;
use App\Enums\TimeWindowUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Achievement extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'description',
        'icon_url',
        'event_type',
        'aggregator_type',
        'threshold',
        'time_window_value',
        'time_window_unit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'event_type'      => EventType::class,
            'aggregator_type' => AggregatorType::class,
            'threshold'          => 'decimal:2',
            'time_window_value'  => 'integer',
            'time_window_unit'   => TimeWindowUnit::class,
            'is_active'          => 'boolean',
        ];
    }

    // === Relationships ===

    public function userAchievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    // === Scopes ===

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForEvent(Builder $query, EventType $type): Builder
    {
        return $query->where('event_type', $type->value);
    }
}