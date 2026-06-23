<?php

namespace App\Services\Achievements\Aggregations;

use App\Enums\EventType;
use App\Models\User;
use App\Services\Achievements\Criteria\Criterion;
use App\Support\JsonMapping\JsonField;
use App\Support\JsonMapping\JsonType;
use Illuminate\Database\Eloquent\Builder;

#[JsonType(field: 'type', subtypes: [
    CountAggregation::class,
    TotalAggregation::class,
    UniqueCountAggregation::class,
    SequenceAggregation::class,
])]
abstract class Aggregation
{
    public function __construct(
        #[JsonField(arrayOf: Criterion::class)]
        public array $criteria = [],
    ) {}

    protected function buildQuery(User $user, EventType $eventType): Builder
    {
        $query = $user->events()->where('type', $eventType->value)->getQuery();

        foreach ($this->criteria as $criterion) {
            $query = $criterion->apply($query);
        }

        return $query;
    }

    abstract public function evaluate(User $user, EventType $eventType): int|float;
}
