<?php

namespace Tests\Unit\Support\JsonMapping\Fixtures;

use App\Support\JsonMapping\JsonField;
use App\Support\JsonMapping\JsonSubType;
use App\Support\JsonMapping\JsonType;

#[JsonType(field: 'kind', subtypes: [
    Circle::class,
    Rectangle::class,
    FilledCircle::class,
])]
#[JsonSubType('filled_circle')]
class FilledCircle extends Circle
{
    public function __construct(
        string $name,
        float $radius,
        #[JsonField(name: 'fill_color')]
        public Color $fillColor,
    ) {
        parent::__construct($name, $radius);
    }
}
