<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Duel\Enums\ChatRoomType;
use App\Domains\Duel\Models\ChatRoom;
use App\Domains\Duel\Models\Conversation;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
final class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_room_id' => ChatRoom::factory()->state(['type' => ChatRoomType::DIRECT_MESSAGE]),
            'user_id' => User::factory(),
            'last_read_at' => null,
            'is_muted' => false,
            'is_blocked' => false,
        ];
    }

    /**
     * Set the chat room.
     */
    public function inRoom(ChatRoom $room): static
    {
        return $this->state(fn (array $attributes): array => [
            'chat_room_id' => $room->id,
        ]);
    }

    /**
     * Set the user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Mark as read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes): array => [
            'last_read_at' => now(),
        ]);
    }

    /**
     * Mark as muted.
     */
    public function muted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_muted' => true,
        ]);
    }

    /**
     * Mark as blocked.
     */
    public function blocked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_blocked' => true,
        ]);
    }
}
