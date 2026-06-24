<?php

namespace App\Support\JsonMapping;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class JsonField
{
    public function __construct(
        public ?string $name = null,
        public ?string $arrayOf = null,
    ) {}
}
