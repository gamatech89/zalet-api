<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Wallet\Models\LedgerEntry;
use App\Domains\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerEntry>
 */
final class LedgerEntryFactory extends Factory
{
    protected $model = LedgerEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = $this->faker->numberBetween(10, 1000);

        return [
            'wallet_id' => Wallet::factory(),
            'type' => $this->faker->randomElement([
                LedgerEntry::TYPE_DEPOSIT,
                LedgerEntry::TYPE_GIFT_RECEIVED,
            ]),
            'amount' => $amount,
            'balance_after' => $amount,
            'reference_type' => null,
            'reference_id' => null,
            'description' => $this->faker->optional()->sentence(),
            'meta' => [],
        ];
    }

    /**
     * Create a deposit entry.
     */
    public function deposit(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => LedgerEntry::TYPE_DEPOSIT,
        ]);
    }

    /**
     * Create a withdrawal entry.
     */
    public function withdrawal(): static
    {
        return $this->state(function (array $attributes): array {
            $amount = abs($attributes['amount'] ?? 100);
            return [
                'type' => LedgerEntry::TYPE_WITHDRAWAL,
                'amount' => -$amount,
            ];
        });
    }

    /**
     * Create a gift sent entry.
     */
    public function giftSent(): static
    {
        return $this->state(function (array $attributes): array {
            $amount = abs($attributes['amount'] ?? 100);
            return [
                'type' => LedgerEntry::TYPE_GIFT_SENT,
                'amount' => -$amount,
            ];
        });
    }

    /**
     * Create a gift received entry.
     */
    public function giftReceived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => LedgerEntry::TYPE_GIFT_RECEIVED,
        ]);
    }

    /**
     * Create entry for specific wallet.
     */
    public function forWallet(Wallet $wallet): static
    {
        return $this->state(fn (array $attributes): array => [
            'wallet_id' => $wallet->id,
        ]);
    }

    /**
     * Create entry with specific amount.
     */
    public function withAmount(int $amount): static
    {
        return $this->state(fn (array $attributes): array => [
            'amount' => $amount,
        ]);
    }

    /**
     * Create entry with specific balance after.
     */
    public function withBalanceAfter(int $balance): static
    {
        return $this->state(fn (array $attributes): array => [
            'balance_after' => $balance,
        ]);
    }
}
