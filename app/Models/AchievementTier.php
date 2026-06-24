<?php

namespace App\Models;

use App\Services\Achievements\Rewards\RewardCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AchievementTier extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'achievement_id',
        'level',
        'threshold',
        'icon',
        'reward',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'threshold' => 'integer',
            'reward' => RewardCast::class,
        ];
    }

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievement_tiers')
            ->withPivot('progress', 'unlocked_at', 'collected_at')
            ->withTimestamps();
    }

    public function scopeUnresolvedForUser(Builder $query, User $user): Builder
    {
        return $query->whereHas('achievement', fn (Builder $q) => $q->where('is_enabled', true))
            ->whereDoesntHave('users', function (Builder $q) use ($user) {
                $q->where('user_id', $user->id)
                    ->whereNotNull('unlocked_at');
            });
    }

    public function isFulfilledBy(int|float $value): bool
    {
        return $value >= $this->threshold;
    }

    public function awardTo(User $user, int|float $progress): void
    {
        $existing = $this->users()->where('user_id', $user->id)->first();

        if ($existing && $existing->pivot->unlocked_at) {
            return;
        }

        $unlocked = $this->isFulfilledBy($progress);

        $this->users()->syncWithoutDetaching([
            $user->id => [
                'progress' => $progress,
                'unlocked_at' => $unlocked ? now() : null,
            ],
        ]);
    }
}
