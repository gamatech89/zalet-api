<?php

namespace App\Models;

use App\Enums\EventType;
use App\Services\Achievements\Aggregations\AggregationCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Achievement extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'event_type',
        'aggregation',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => EventType::class,
            'aggregation' => AggregationCast::class,
            'is_enabled' => 'boolean',
        ];
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(AchievementTier::class)->orderBy('level');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
