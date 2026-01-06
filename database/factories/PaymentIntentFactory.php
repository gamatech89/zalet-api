<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Models\User;
use App\Domains\Wallet\Models\PaymentIntent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentIntent>
 */
final class PaymentIntentFactory extends Factory
{
    protected $model = PaymentIntent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $packages = config('services.credit_packages', []);
        $package = $this->faker->randomElement($packages) ?: [
            'id' => 'starter',
            'name' => 'Starter',
            'credits' => 100,
            'price_cents' => 500,
            'currency' => 'EUR',
        ];

        return [
            'uuid' => Str::uuid()->toString(),
            'user_id' => User::factory(),
            'provider' => 'raiaccept_stub',
            'provider_order_id' => 'order_' . Str::random(16),
            'provider_session_url' => 'https://stub.raiaccept.local/pay/' . Str::random(16),
            'amount_cents' => $package['price_cents'],
            'currency' => $package['currency'],
            'credits_amount' => $package['credits'],
            'status' => PaymentIntent::STATUS_PENDING,
            'idempotency_key' => Str::uuid()->toString(),
            'meta' => [
                'package_id' => $package['id'],
                'package_name' => $package['name'],
                'language' => 'sr',
            ],
        ];
    }

    /**
     * Indicate that the payment intent is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentIntent::STATUS_PENDING,
        ]);
    }

    /**
     * Indicate that the payment intent is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentIntent::STATUS_PROCESSING,
            'webhook_received_at' => now(),
        ]);
    }

    /**
     * Indicate that the payment intent is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentIntent::STATUS_COMPLETED,
            'webhook_received_at' => now()->subSeconds(10),
        ]);
    }

    /**
     * Indicate that the payment intent is failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentIntent::STATUS_FAILED,
            'webhook_received_at' => now(),
            'meta' => [
                'failure_reason' => 'card_declined',
                'failure_message' => 'The card was declined',
            ],
        ]);
    }

    /**
     * Indicate that the payment intent is refunded.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentIntent::STATUS_REFUNDED,
            'webhook_received_at' => now()->subMinutes(30),
            'meta' => [
                'refund_id' => 'refund_' . Str::random(16),
                'refund_reason' => 'requested_by_customer',
            ],
        ]);
    }

    /**
     * Indicate that the payment intent is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentIntent::STATUS_CANCELLED,
        ]);
    }

    /**
     * Set the payment intent to use a specific package.
     */
    public function forPackage(string $packageId): static
    {
        /** @var array<int, array{id: string, name: string, credits: int, price_cents: int, currency: string}> $packages */
        $packages = config('services.credit_packages', []);

        $package = null;
        foreach ($packages as $p) {
            if ($p['id'] === $packageId) {
                $package = $p;
                break;
            }
        }

        if ($package === null) {
            throw new \InvalidArgumentException("Package {$packageId} not found");
        }

        return $this->state(fn (array $attributes) => [
            'amount_cents' => $package['price_cents'],
            'currency' => $package['currency'],
            'credits_amount' => $package['credits'],
            'meta' => array_merge($attributes['meta'] ?? [], [
                'package_id' => $package['id'],
                'package_name' => $package['name'],
            ]),
        ]);
    }

    /**
     * Set the payment intent for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
