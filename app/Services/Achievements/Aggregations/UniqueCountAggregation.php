<?php

namespace App\Services\Achievements\Aggregations;

use App\Enums\AggregationType;
use App\Enums\EventType;
use App\Models\User;
use App\Services\Achievements\Criteria\Criterion;
use App\Support\JsonMapping\JsonField;
use App\Support\JsonMapping\JsonSubType;

#[JsonSubType(AggregationType::UNIQUE_COUNT)]
class UniqueCountAggregation extends Aggregation
{
    public function __construct(
        #[JsonField(arrayOf: Criterion::class)]
        array $criteria,
        #[JsonField(name: 'target_field')]
        public string $targetField,
    ) {
        parent::__construct($criteria);
    }

    public function evaluate(User $user, EventType $eventType): int
    {
        return $this->buildQuery($user, $eventType)->distinct("data->{$this->targetField}")->count("data->{$this->targetField}");
    }
}
