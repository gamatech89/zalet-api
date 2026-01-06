<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Models\User;
use App\Domains\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
final class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'balance' => $this->faker->numberBetween(0, 10000),
            'currency' => 'CREDITS',
        ];
    }

    /**
     * Create wallet with zero balance.
     */
    public function empty(): static
    {
        return $this->state(fn (array $attributes): array => [
            'balance' => 0,
        ]);
    }

    /**
     * Create wallet with specific balance.
     */
    public function withBalance(int $balance): static
    {
        return $this->state(fn (array $attributes): array => [
            'balance' => $balance,
        ]);
    }

    /**
     * Create wallet for specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->id,
        ]);
    }
}
