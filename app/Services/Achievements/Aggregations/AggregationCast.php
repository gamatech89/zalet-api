<?php

namespace App\Services\Achievements\Aggregations;

use App\Support\JsonMapping\JsonTypeCast;

class AggregationCast extends JsonTypeCast
{
    public function __construct()
    {
        parent::__construct(Aggregation::class);
    }
}
