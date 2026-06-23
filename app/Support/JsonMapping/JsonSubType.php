<?php

namespace App\Support\JsonMapping;

use Attribute;
use BackedEnum;

#[Attribute(Attribute::TARGET_CLASS)]
class JsonSubType
{
    public string $value;

    public function __construct(string|BackedEnum $value)
    {
        $this->value = $value instanceof BackedEnum ? (string) $value->value : $value;
    }
}
