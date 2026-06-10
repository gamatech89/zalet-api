<?php

namespace App\Services\Aggregators;

use App\Models\Achievement;

interface AggregatorInterface
{
    public function compute(string $userId, Achievement $achievement): float;
}