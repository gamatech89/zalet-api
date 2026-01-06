<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'email' => fake()->unique()->safeEmail() . '.' . Str::random(6),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::User,
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create user with admin role.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Admin,
        ]);
    }

    /**
     * Create user with creator role.
     */
    public function creator(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Creator,
        ]);
    }
}
