<?php

namespace Tests\Unit\Support\JsonMapping;

use App\Support\JsonMapping\JsonTypeConverter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\JsonMapping\Fixtures\Canvas;
use Tests\Unit\Support\JsonMapping\Fixtures\Circle;
use Tests\Unit\Support\JsonMapping\Fixtures\Color;
use Tests\Unit\Support\JsonMapping\Fixtures\FilledCircle;
use Tests\Unit\Support\JsonMapping\Fixtures\Rectangle;
use Tests\Unit\Support\JsonMapping\Fixtures\Shape;

class JsonTypeConverterTest extends TestCase
{
    public function test_hydrates_simple_subtype(): void
    {
        $result = JsonTypeConverter::fromArray(Shape::class, [
            'kind' => 'circle',
            'name' => 'my circle',
            'radius' => 5.0,
        ]);

        $this->assertInstanceOf(Circle::class, $result);
        $this->assertEquals('my circle', $result->name);
        $this->assertEquals(5.0, $result->radius);
    }

    public function test_hydrates_subtype_with_json_field_rename(): void
    {
        $result = JsonTypeConverter::fromArray(Shape::class, [
            'kind' => 'rectangle',
            'name' => 'my rect',
            'width' => 10.0,
            'height' => 20.0,
            'fill_color' => 'blue',
        ]);

        $this->assertInstanceOf(Rectangle::class, $result);
        $this->assertEquals('my rect', $result->name);
        $this->assertEquals(10.0, $result->width);
        $this->assertEquals(20.0, $result->height);
        $this->assertEquals(Color::BLUE, $result->fillColor);
    }

    public function test_hydrates_with_default_values(): void
    {
        $result = JsonTypeConverter::fromArray(Shape::class, [
            'kind' => 'rectangle',
            'name' => 'default rect',
            'width' => 10.0,
            'height' => 20.0,
        ]);

        $this->assertInstanceOf(Rectangle::class, $result);
        $this->assertEquals(Color::RED, $result->fillColor);
    }

    public function test_hydrates_nested_array_of_typed_objects(): void
    {
        $result = JsonTypeConverter::fromArray(Canvas::class, [
            'type' => 'canvas',
            'title' => 'my drawing',
            'shapes' => [
                ['kind' => 'circle', 'name' => 'c1', 'radius' => 3.0],
                ['kind' => 'rectangle', 'name' => 'r1', 'width' => 5.0, 'height' => 10.0],
            ],
        ]);

        $this->assertInstanceOf(Canvas::class, $result);
        $this->assertEquals('my drawing', $result->title);
        $this->assertCount(2, $result->shapes);
        $this->assertInstanceOf(Circle::class, $result->shapes[0]);
        $this->assertInstanceOf(Rectangle::class, $result->shapes[1]);
    }

    public function test_hydrates_multi_level_inheritance(): void
    {
        $result = JsonTypeConverter::fromArray(FilledCircle::class, [
            'kind' => 'filled_circle',
            'name' => 'fancy circle',
            'radius' => 7.5,
            'fill_color' => 'blue',
        ]);

        $this->assertInstanceOf(FilledCircle::class, $result);
        $this->assertEquals('fancy circle', $result->name);
        $this->assertEquals(7.5, $result->radius);
        $this->assertEquals(Color::BLUE, $result->fillColor);
    }

    public function test_serializes_to_array(): void
    {
        $circle = new Circle('test circle', 5.0);
        $result = JsonTypeConverter::toArray($circle);

        $this->assertEquals([
            'kind' => 'circle',
            'name' => 'test circle',
            'radius' => 5.0,
        ], $result);
    }

    public function test_serializes_enum_to_value(): void
    {
        $rect = new Rectangle('test rect', 10.0, 20.0, Color::BLUE);
        $result = JsonTypeConverter::toArray($rect);

        $this->assertEquals([
            'kind' => 'rectangle',
            'name' => 'test rect',
            'width' => 10.0,
            'height' => 20.0,
            'fill_color' => 'blue',
        ], $result);
    }

    public function test_serializes_nested_typed_arrays(): void
    {
        $canvas = new Canvas('drawing', [
            new Circle('c1', 3.0),
            new Rectangle('r1', 5.0, 10.0),
        ]);

        $result = JsonTypeConverter::toArray($canvas);

        $this->assertEquals('canvas', $result['type']);
        $this->assertEquals('drawing', $result['title']);
        $this->assertCount(2, $result['shapes']);
        $this->assertEquals('circle', $result['shapes'][0]['kind']);
        $this->assertEquals('rectangle', $result['shapes'][1]['kind']);
    }

    public function test_round_trip(): void
    {
        $original = [
            'type' => 'canvas',
            'title' => 'round trip',
            'shapes' => [
                ['kind' => 'circle', 'name' => 'c1', 'radius' => 3.0],
                ['kind' => 'rectangle', 'name' => 'r1', 'width' => 5.0, 'height' => 10.0, 'fill_color' => 'blue'],
            ],
        ];

        $object = JsonTypeConverter::fromArray(Canvas::class, $original);
        $serialized = JsonTypeConverter::toArray($object);

        $this->assertEquals($original['type'], $serialized['type']);
        $this->assertEquals($original['title'], $serialized['title']);
        $this->assertCount(2, $serialized['shapes']);
        $this->assertEquals('circle', $serialized['shapes'][0]['kind']);
        $this->assertEquals('rectangle', $serialized['shapes'][1]['kind']);
        $this->assertEquals('blue', $serialized['shapes'][1]['fill_color']);
    }

    public function test_throws_on_missing_discriminator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing discriminator field');

        JsonTypeConverter::fromArray(Shape::class, [
            'name' => 'no type',
            'radius' => 5.0,
        ]);
    }

    public function test_throws_on_unknown_subtype(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown subtype');

        JsonTypeConverter::fromArray(Shape::class, [
            'kind' => 'triangle',
            'name' => 'bad',
        ]);
    }

    public function test_throws_on_missing_required_field(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field');

        JsonTypeConverter::fromArray(Shape::class, [
            'kind' => 'circle',
            'name' => 'no radius',
        ]);
    }

    public function test_throws_on_invalid_enum_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid enum value');

        JsonTypeConverter::fromArray(Shape::class, [
            'kind' => 'rectangle',
            'name' => 'bad color',
            'width' => 10.0,
            'height' => 20.0,
            'fill_color' => 'green',
        ]);
    }
}
