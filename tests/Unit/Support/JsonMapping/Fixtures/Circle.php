<?php

namespace Tests\Unit\Support\JsonMapping\Fixtures;

use App\Support\JsonMapping\JsonSubType;

#[JsonSubType('circle')]
class Circle extends Shape
{
    public function __construct(
        string $name,
        public float $radius,
    ) {
        parent::__construct($name);
    }
}
