<?php

namespace Tests\Unit\Support\JsonMapping\Fixtures;

use App\Support\JsonMapping\JsonField;
use App\Support\JsonMapping\JsonSubType;
use App\Support\JsonMapping\JsonType;

#[JsonType(field: 'type', subtypes: [
    Canvas::class,
])]
#[JsonSubType('canvas')]
class Canvas
{
    public function __construct(
        public string $title,
        #[JsonField(arrayOf: Shape::class)]
        public array $shapes,
    ) {}
}
