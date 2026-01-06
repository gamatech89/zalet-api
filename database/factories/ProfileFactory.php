<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Models\Profile;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
final class ProfileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Profile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'username' => fake()->unique()->userName(),
            'display_name' => fake()->name(),
            'bio' => fake()->optional()->sentence(),
            'avatar_url' => null,
            'is_private' => false,
            'origin_location_id' => null,
            'current_location_id' => null,
        ];
    }

    /**
     * Indicate that the profile should be private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_private' => true,
        ]);
    }
}
