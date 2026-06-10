<?php

namespace App\Services\Aggregators;

use App\Models\Achievement;

class MaxAggregator extends EventAggregator
{
    public function compute(string $userId, Achievement $achievement): float
    {
        return (float) $this->baseQuery($userId, $achievement)->max('value');
    }
}