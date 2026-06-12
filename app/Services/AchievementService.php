<?php

namespace App\Services;

use App\Enums\AggregatorType;
use App\Enums\EventType;
use App\Jobs\EvaluateAchievementsJob;
use App\Models\Achievement;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserEvent;
use App\Services\Aggregators\CountAggregator;
use App\Services\Aggregators\MaxAggregator;
use App\Services\Aggregators\StreakAggregator;
use App\Services\Aggregators\SumAggregator;

class AchievementService
{
    public function record(User $user, EventType $type, ?float $value = null, array $metadata = []): UserEvent
    {
        $event = UserEvent::create([
            'user_id'     => $user->id,
            'event_type'  => $type->value,
            'value'       => $value,
            'metadata'    => $metadata ?: null,
            'occurred_at' => now(),
        ]);

        EvaluateAchievementsJob::dispatch($user->id, $type)->afterCommit();

        return $event;
    }

    public function evaluate(string $userId, EventType $type): void
    {
        Achievement::active()
            ->forEvent($type)
            ->each(function (Achievement $achievement) use ($userId) {
                $record = UserAchievement::firstOrNew([
                    'user_id'        => $userId,
                    'achievement_id' => $achievement->id,
                ]);

                if ($record->earned_at !== null) {
                    return;
                }

                $progress = $this->aggregate($achievement, $userId);

                $record->progress  = $progress;
                $record->earned_at = $progress >= (float) $achievement->threshold ? now() : null;
                $record->save();
            });
    }

    private function aggregate(Achievement $achievement, string $userId): float
    {
        return match ($achievement->aggregator_type) {
            AggregatorType::Count  => (new CountAggregator())->compute($userId, $achievement),
            AggregatorType::Sum    => (new SumAggregator())->compute($userId, $achievement),
            AggregatorType::Max    => (new MaxAggregator())->compute($userId, $achievement),
            AggregatorType::Streak => (new StreakAggregator())->compute($userId, $achievement),
        };
    }
}