<?php

namespace App\Services\Achievements\Aggregations;

use App\Enums\AggregationType;
use App\Enums\EventType;
use App\Models\User;
use App\Services\Achievements\Criteria\Criterion;
use App\Support\JsonMapping\JsonField;
use App\Support\JsonMapping\JsonSubType;

#[JsonSubType(AggregationType::TOTAL)]
class TotalAggregation extends Aggregation
{
    public function __construct(
        #[JsonField(arrayOf: Criterion::class)]
        array $criteria,
        #[JsonField(name: 'target_field')]
        public string $targetField,
    ) {
        parent::__construct($criteria);
    }

    public function evaluate(User $user, EventType $eventType): int|float
    {
        return (float) $this->buildQuery($user, $eventType)->sum("data->{$this->targetField}");
    }
}
