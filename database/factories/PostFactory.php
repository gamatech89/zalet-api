<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Enums\PostType;
use App\Domains\Shared\Enums\VideoProvider;
use App\Domains\Streaming\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
final class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $provider = $this->faker->randomElement([VideoProvider::YouTube, VideoProvider::Vimeo]);
        $providerId = $this->generateProviderId($provider);

        return [
            'user_id' => User::factory(),
            'type' => PostType::Video,
            'title' => $this->faker->sentence(6),
            'description' => $this->faker->optional(0.8)->paragraph(),
            'source_url' => $this->generateSourceUrl($provider, $providerId),
            'provider' => $provider,
            'provider_id' => $providerId,
            'thumbnail_url' => $provider->getThumbnailUrl($providerId),
            'duration_seconds' => $this->faker->optional(0.7)->numberBetween(30, 7200),
            'is_premium' => false,
            'is_published' => true,
            'published_at' => now(),
            'meta' => [],
        ];
    }

    /**
     * Create a YouTube video post.
     */
    public function youtube(): static
    {
        return $this->state(function (array $attributes): array {
            $providerId = $this->generateProviderId(VideoProvider::YouTube);

            return [
                'provider' => VideoProvider::YouTube,
                'provider_id' => $providerId,
                'source_url' => "https://www.youtube.com/watch?v={$providerId}",
                'thumbnail_url' => VideoProvider::YouTube->getThumbnailUrl($providerId),
            ];
        });
    }

    /**
     * Create a Vimeo video post.
     */
    public function vimeo(): static
    {
        return $this->state(function (array $attributes): array {
            $providerId = $this->generateProviderId(VideoProvider::Vimeo);

            return [
                'provider' => VideoProvider::Vimeo,
                'provider_id' => $providerId,
                'source_url' => "https://vimeo.com/{$providerId}",
                'thumbnail_url' => VideoProvider::Vimeo->getThumbnailUrl($providerId),
            ];
        });
    }

    /**
     * Create a short clip post.
     */
    public function shortClip(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => PostType::ShortClip,
            'duration_seconds' => $this->faker->numberBetween(15, 60),
        ]);
    }

    /**
     * Create an image post.
     */
    public function image(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => PostType::Image,
            'provider' => null,
            'provider_id' => null,
            'source_url' => $this->faker->imageUrl(1280, 720),
            'thumbnail_url' => $this->faker->imageUrl(640, 360),
            'duration_seconds' => null,
        ]);
    }

    /**
     * Create a premium post.
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_premium' => true,
        ]);
    }

    /**
     * Create an unpublished (draft) post.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    /**
     * Create a post published at a specific time.
     */
    public function publishedAt(\DateTimeInterface $date): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_published' => true,
            'published_at' => $date,
        ]);
    }

    /**
     * Create a post with specific duration.
     */
    public function withDuration(int $seconds): static
    {
        return $this->state(fn (array $attributes): array => [
            'duration_seconds' => $seconds,
        ]);
    }

    /**
     * Create a post for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Generate a provider ID based on provider type.
     */
    private function generateProviderId(VideoProvider $provider): string
    {
        return match ($provider) {
            VideoProvider::YouTube => $this->faker->regexify('[a-zA-Z0-9_-]{11}'),
            VideoProvider::Vimeo => (string) $this->faker->numberBetween(100000000, 999999999),
            VideoProvider::Mux => $this->faker->uuid(),
            VideoProvider::Local => $this->faker->uuid(),
        };
    }

    /**
     * Generate a source URL based on provider and ID.
     */
    private function generateSourceUrl(VideoProvider $provider, string $providerId): string
    {
        return match ($provider) {
            VideoProvider::YouTube => "https://www.youtube.com/watch?v={$providerId}",
            VideoProvider::Vimeo => "https://vimeo.com/{$providerId}",
            VideoProvider::Mux => "https://stream.mux.com/{$providerId}",
            VideoProvider::Local => "/storage/videos/{$providerId}",
        };
    }
}
