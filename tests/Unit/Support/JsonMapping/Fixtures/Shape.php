<?php

namespace Tests\Unit\Support\JsonMapping\Fixtures;

use App\Support\JsonMapping\JsonType;

#[JsonType(field: 'kind', subtypes: [
    Circle::class,
    Rectangle::class,
])]
abstract class Shape
{
    public function __construct(
        public string $name,
    ) {}
}
