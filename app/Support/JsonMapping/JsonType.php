<?php

namespace App\Support\JsonMapping;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class JsonType
{
    public function __construct(
        public string $field = 'type',
        public array $subtypes = [],
    ) {}
}
