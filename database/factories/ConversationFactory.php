<?php

namespace Database\Factories;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => null,
            'is_group' => false,
        ];
    }

    /**
     * Indicate this is a group conversation.
     */
    public function group(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_group' => true,
            'name' => fake()->words(3, true),
        ]);
    }
}
