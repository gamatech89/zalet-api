<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
final class LocationFactory extends Factory
{
    protected $model = Location::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'city' => $this->faker->city(),
            'country' => 'Serbia',
            'country_code' => 'RS',
            'latitude' => $this->faker->latitude(42.0, 46.5),
            'longitude' => $this->faker->longitude(18.8, 23.0),
        ];
    }

    /**
     * Create a Belgrade location.
     */
    public function belgrade(): static
    {
        return $this->state(fn (array $attributes): array => [
            'city' => 'Belgrade',
            'country' => 'Serbia',
            'country_code' => 'RS',
            'latitude' => 44.7866,
            'longitude' => 20.4489,
        ]);
    }

    /**
     * Create a Novi Sad location.
     */
    public function noviSad(): static
    {
        return $this->state(fn (array $attributes): array => [
            'city' => 'Novi Sad',
            'country' => 'Serbia',
            'country_code' => 'RS',
            'latitude' => 45.2671,
            'longitude' => 19.8335,
        ]);
    }

    /**
     * Create a location without coordinates.
     */
    public function withoutCoordinates(): static
    {
        return $this->state(fn (array $attributes): array => [
            'latitude' => null,
            'longitude' => null,
        ]);
    }
}
