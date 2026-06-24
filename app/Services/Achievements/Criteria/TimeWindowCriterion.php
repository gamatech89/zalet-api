<?php

namespace App\Services\Achievements\Criteria;

use App\Support\JsonMapping\JsonSubType;
use Illuminate\Database\Eloquent\Builder;

#[JsonSubType('time_window')]
class TimeWindowCriterion extends Criterion
{
    public function __construct(
        public int $value,
        public string $unit = 'days',
    ) {}

    public function apply(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->sub($this->unit, $this->value));
    }
}
