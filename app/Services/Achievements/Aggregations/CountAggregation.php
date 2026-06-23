<?php

namespace App\Services\Achievements\Aggregations;

use App\Enums\AggregationType;
use App\Enums\EventType;
use App\Models\User;
use App\Support\JsonMapping\JsonSubType;

#[JsonSubType(AggregationType::COUNT)]
class CountAggregation extends Aggregation
{
    public function evaluate(User $user, EventType $eventType): int
    {
        return $this->buildQuery($user, $eventType)->count();
    }
}
