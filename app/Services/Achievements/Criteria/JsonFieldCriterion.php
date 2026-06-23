<?php

namespace App\Services\Achievements\Criteria;

use App\Support\JsonMapping\JsonSubType;
use Illuminate\Database\Eloquent\Builder;

#[JsonSubType('json_field')]
class JsonFieldCriterion extends Criterion
{
    public function __construct(
        public string $field,
        public mixed $value,
        public string $operator = '=',
    ) {}

    public function apply(Builder $query): Builder
    {
        return $query->where("data->{$this->field}", $this->operator, $this->value);
    }
}
