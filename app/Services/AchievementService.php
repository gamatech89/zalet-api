<?php

namespace App\Services;

use App\Enums\AchievementState;
use App\Models\Achievement;
use App\Models\AchievementTier;
use App\Models\User;
use App\Services\Achievements\Rewards\Reward;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AchievementService
{
    public function create(array $data): Achievement
    {
        return DB::transaction(function () use ($data) {
            $achievement = Achievement::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'icon' => $data['icon'] ?? null,
                'event_type' => $data['event_type'],
                'aggregation' => $data['aggregation'],
            ]);

            $this->syncTiers($achievement, $data['tiers']);

            return $achievement->load('tiers');
        });
    }

    public function update(Achievement $achievement, array $data): Achievement
    {
        return DB::transaction(function () use ($achievement, $data) {
            $achievement->update(
                collect($data)->only(['name', 'description', 'icon', 'event_type', 'aggregation', 'is_enabled'])->toArray()
            );

            if (isset($data['tiers'])) {
                $this->syncTiers($achievement, $data['tiers']);
            }

            return $achievement->load('tiers');
        });
    }

    public function delete(Achievement $achievement): void
    {
        $achievement->delete();
    }

    public function list(): Collection
    {
        return Achievement::with('tiers')->orderBy('created_at', 'desc')->get();
    }

    public function getUserAchievements(User $user): Collection
    {
        $achievements = Achievement::enabled()
            ->with(['tiers' => function ($query) use ($user) {
                $query->orderBy('level')
                    ->with(['users' => fn ($q) => $q->where('user_id', $user->id)]);
            }])
            ->get();

        return $achievements->map(fn (Achievement $achievement) => $this->resolveState($achievement));
    }

    public function collect(User $user, AchievementTier $tier): Reward
    {
        $pivot = $tier->users()->where('user_id', $user->id)->first();

        if (! $pivot || ! $pivot->pivot->unlocked_at) {
            throw new RuntimeException('Achievement not unlocked.');
        }

        if ($pivot->pivot->collected_at) {
            throw new RuntimeException('Reward already collected.');
        }

        if (! $tier->reward) {
            throw new RuntimeException('No reward to collect.');
        }

        $tier->users()->updateExistingPivot($user->id, [
            'collected_at' => now(),
        ]);

        $tier->reward->grant($user);

        return $tier->reward;
    }

    private function syncTiers(Achievement $achievement, array $tiers): void
    {
        $this->validateTierThresholds($tiers);

        $existing = $achievement->tiers()->get()->keyBy('id');
        $incomingIds = collect($tiers)->pluck('id')->filter()->all();
        $toDelete = $existing->keys()->diff($incomingIds);

        if ($toDelete->isNotEmpty()) {
            $achievement->tiers()->whereIn('id', $toDelete)->delete();
        }

        foreach ($tiers as $index => $tierData) {
            $payload = [
                'level' => $index + 1,
                'threshold' => $tierData['threshold'],
                'icon' => $tierData['icon'] ?? null,
                'reward' => $tierData['reward'] ?? null,
            ];

            if (isset($tierData['id']) && $existing->has($tierData['id'])) {
                $existing[$tierData['id']]->update($payload);
            } else {
                $achievement->tiers()->create($payload);
            }
        }
    }

    private function validateTierThresholds(array $tiers): void
    {
        $previousThreshold = 0;

        foreach ($tiers as $index => $tier) {
            $level = $index + 1;

            if ($tier['threshold'] <= $previousThreshold) {
                throw new RuntimeException("Tier {$level} threshold must be higher than tier " . ($level - 1) . '.');
            }

            $previousThreshold = $tier['threshold'];
        }
    }

    private function resolveState(Achievement $achievement): array
    {
        $tiers = $achievement->tiers;
        $currentLevel = 0;
        $claimable = null;
        $nextTier = null;

        foreach ($tiers as $tier) {
            $pivot = $tier->users->first()?->pivot;

            if ($pivot && $pivot->unlocked_at) {
                if ($tier->reward && ! $pivot->collected_at) {
                    $claimable = $claimable ?? $tier;
                } else {
                    $currentLevel = max($currentLevel, $tier->level);
                }
            } else {
                $nextTier = $nextTier ?? $tier;
            }
        }

        if ($claimable) {
            $state = AchievementState::CLAIMABLE;
        } elseif ($nextTier) {
            $state = AchievementState::IN_PROGRESS;
        } else {
            $state = AchievementState::COMPLETED;
        }

        return [
            'id' => $achievement->id,
            'name' => $achievement->name,
            'description' => $achievement->description,
            'icon' => $achievement->icon,
            'max_level' => $tiers->count(),
            'current_level' => $currentLevel,
            'state' => $state->value,
            'claimable' => $claimable ? [
                'id' => $claimable->id,
                'level' => $claimable->level,
                'threshold' => $claimable->threshold,
                'reward' => $claimable->reward,
            ] : null,
            'next_tier' => $nextTier ? [
                'level' => $nextTier->level,
                'threshold' => $nextTier->threshold,
                'progress' => $nextTier->users->first()?->pivot?->progress ?? 0,
            ] : null,
        ];
    }
}
