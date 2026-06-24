<?php

namespace Tests\Unit\Support\JsonMapping\Fixtures;

use App\Support\JsonMapping\JsonField;
use App\Support\JsonMapping\JsonSubType;

#[JsonSubType('rectangle')]
class Rectangle extends Shape
{
    public function __construct(
        string $name,
        public float $width,
        public float $height,
        #[JsonField(name: 'fill_color')]
        public Color $fillColor = Color::RED,
    ) {
        parent::__construct($name);
    }
}
