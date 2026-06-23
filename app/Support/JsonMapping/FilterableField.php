<?php

namespace App\Support\JsonMapping;

use App\Enums\FilterableEntity;
use App\Enums\FilterableFieldType;
use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class FilterableField
{
    public function __construct(
        public string $key,
        public FilterableFieldType $type,
        public ?FilterableEntity $entity = null,
        public ?string $values = null,
    ) {}
}
