<?php

namespace App\Services\Aggregators;

use App\Models\Achievement;
use App\Models\UserEvent;
use Illuminate\Database\Eloquent\Builder;

abstract class EventAggregator implements AggregatorInterface
{
    protected function baseQuery(string $userId, Achievement $achievement): Builder
    {
        return UserEvent::where('user_id', $userId)
            ->where('event_type', $achievement->event_type->value)
            ->when(
                $achievement->time_window_value && $achievement->time_window_unit,
                fn($q) => $q->where('occurred_at', '>=', now()->sub($achievement->time_window_unit->value, $achievement->time_window_value))
            );
    }

    abstract public function compute(string $userId, Achievement $achievement): float;
}