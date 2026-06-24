<?php

namespace App\Services\Achievements\Aggregations;

use App\Enums\AggregationType;
use App\Enums\EventType;
use App\Models\User;
use App\Services\Achievements\Criteria\Criterion;
use App\Support\JsonMapping\JsonField;
use App\Support\JsonMapping\JsonSubType;
use Carbon\Carbon;

#[JsonSubType(AggregationType::SEQUENCE)]
class SequenceAggregation extends Aggregation
{
    public function __construct(
        #[JsonField(arrayOf: Criterion::class)]
        array $criteria,
        #[JsonField(name: 'interval_unit')]
        public string $intervalUnit,
        #[JsonField(name: 'interval_value')]
        public int $intervalValue,
    ) {
        parent::__construct($criteria);
    }

    public function evaluate(User $user, EventType $eventType): int
    {
        $timestamps = $this->buildQuery($user, $eventType)
            ->selectRaw('DATE(created_at) as event_date')
            ->groupBy('event_date')
            ->orderByDesc('event_date')
            ->pluck('event_date')
            ->map(fn ($d) => Carbon::parse($d)->startOf($this->intervalUnit));

        $streak = 0;
        $expected = now()->startOf($this->intervalUnit);

        foreach ($timestamps as $ts) {
            if ($ts->eq($expected)) {
                $streak++;
                $expected = $expected->copy()->sub($this->intervalUnit, $this->intervalValue);
            } else {
                break;
            }
        }

        return $streak;
    }
}
