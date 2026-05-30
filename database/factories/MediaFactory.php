<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement(['moment', 'long_form', 'embed']),
            'provider' => 'native',
            'url' => $this->faker->url(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'thumbnail_url' => $this->faker->imageUrl(640, 360),
            'size_bytes' => $this->faker->numberBetween(1024 * 100, 1024 * 1024 * 50),
            'is_ppv' => false,
            'price_coins' => null,
        ];
    }

    /**
     * Indicate that the media is a moment.
     */
    public function moment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'moment',
            'provider' => 'native',
        ]);
    }

    /**
     * Indicate that the media is an embed.
     */
    public function embed(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'embed',
            'provider' => $this->faker->randomElement(['youtube', 'vimeo', 'dailymotion']),
        ]);
    }

    /**
     * Indicate that the media is pay-per-view.
     */
    public function ppv(float $price = 50): static
    {
        return $this->state(fn (array $attributes) => [
            'is_ppv' => true,
            'price_coins' => $price,
        ]);
    }
}
