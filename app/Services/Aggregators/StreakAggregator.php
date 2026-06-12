<?php

namespace App\Services\Aggregators;

use App\Models\Achievement;
use Carbon\Carbon;

class StreakAggregator extends EventAggregator
{
    public function compute(string $userId, Achievement $achievement): float
    {
        $dates = $this->baseQuery($userId, $achievement)
            ->selectRaw('DATE(occurred_at) as event_date')
            ->groupBy('event_date')
            ->orderByDesc('event_date')
            ->pluck('event_date')
            ->map(fn($d) => Carbon::parse($d)->startOfDay());

        $streak   = 0;
        $expected = now()->startOfDay();

        foreach ($dates as $date) {
            if ($date->eq($expected)) {
                $streak++;
                $expected = $expected->copy()->subDay();
            } else {
                break;
            }
        }

        return (float) $streak;
    }
}