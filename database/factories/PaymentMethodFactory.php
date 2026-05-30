<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        $brands = ['visa', 'mastercard', 'dina', 'amex'];

        return [
            'user_id' => User::factory(),
            'card_brand' => $this->faker->randomElement($brands),
            'last_four' => $this->faker->numerify('####'),
            'expiry_month' => str_pad($this->faker->numberBetween(1, 12), 2, '0', STR_PAD_LEFT),
            'expiry_year' => (string) $this->faker->numberBetween(26, 35),
            'gateway_token' => 'tok_test_' . $this->faker->uuid(),
            'is_default' => false,
            'label' => null,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    public function visa(): static
    {
        return $this->state(fn () => ['card_brand' => 'visa']);
    }

    public function mastercard(): static
    {
        return $this->state(fn () => ['card_brand' => 'mastercard']);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expiry_month' => '01',
            'expiry_year' => '20',
        ]);
    }
}
