<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        $banks = ['Banca Intesa', 'Raiffeisen Bank', 'UniCredit Bank', 'Erste Bank', 'Komercijalna Banka'];
        $accountNumber = $this->faker->numerify('################');

        return [
            'user_id' => User::factory(),
            'bank_name' => $this->faker->randomElement($banks),
            'account_number' => $accountNumber,
            'last_four' => substr($accountNumber, -4),
            'is_default' => false,
            'label' => null,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
