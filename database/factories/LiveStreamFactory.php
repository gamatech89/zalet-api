<?php

namespace Database\Factories;

use App\Models\LiveStream;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LiveStream>
 */
class LiveStreamFactory extends Factory
{
    protected $model = LiveStream::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'stream_key' => Str::uuid()->toString(),
            'is_live' => false,
        ];
    }

    /**
     * Indicate the stream is live.
     */
    public function live(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_live' => true,
        ]);
    }
}
