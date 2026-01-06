<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Duel\Enums\ChatRoomType;
use App\Domains\Duel\Models\ChatRoom;
use App\Domains\Identity\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatRoom>
 */
final class ChatRoomFactory extends Factory
{
    protected $model = ChatRoom::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(6),
            'type' => ChatRoomType::PUBLIC_KAFANA,
            'location_id' => null,
            'max_participants' => 500,
            'is_active' => true,
            'meta' => [],
        ];
    }

    /**
     * Set as a public kafana.
     */
    public function kafana(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ChatRoomType::PUBLIC_KAFANA,
        ]);
    }

    /**
     * Set as a private room.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ChatRoomType::PRIVATE,
            'max_participants' => 10,
        ]);
    }

    /**
     * Set as a duel arena.
     */
    public function duel(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ChatRoomType::DUEL,
            'max_participants' => 1000,
        ]);
    }

    /**
     * Set the location.
     */
    public function forLocation(Location $location): static
    {
        return $this->state(fn (array $attributes): array => [
            'location_id' => $location->id,
        ]);
    }

    /**
     * Set as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
