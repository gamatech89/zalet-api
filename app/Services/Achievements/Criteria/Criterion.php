<?php

namespace App\Services\Achievements\Criteria;

use App\Support\JsonMapping\JsonType;
use Illuminate\Database\Eloquent\Builder;

#[JsonType(field: 'type', subtypes: [
    JsonFieldCriterion::class,
    TimeWindowCriterion::class,
])]
abstract class Criterion
{
    abstract public function apply(Builder $query): Builder;
}
