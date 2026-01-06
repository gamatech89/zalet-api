<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Duel\Enums\MessageType;
use App\Domains\Duel\Models\ChatRoom;
use App\Domains\Duel\Models\Message;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Message>
 */
final class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'chat_room_id' => ChatRoom::factory(),
            'user_id' => User::factory(),
            'type' => MessageType::TEXT,
            'content' => fake()->sentence(),
            'meta' => [],
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
    public function fromUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Text message.
     */
    public function text(string $content = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => MessageType::TEXT,
            'content' => $content ?? fake()->sentence(),
        ]);
    }

    /**
     * Gift message.
     */
    public function gift(string $giftSlug, int $creditValue, User $recipient = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => MessageType::GIFT,
            'content' => "Sent a {$giftSlug}!",
            'meta' => [
                'gift_slug' => $giftSlug,
                'credit_value' => $creditValue,
                'recipient_id' => $recipient?->id,
            ],
        ]);
    }

    /**
     * System message.
     */
    public function system(string $content = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => MessageType::SYSTEM,
            'content' => $content ?? 'System notification',
            'user_id' => null,
        ]);
    }

    /**
     * With metadata.
     */
    public function withMeta(array $meta): static
    {
        return $this->state(fn (array $attributes): array => [
            'meta' => $meta,
        ]);
    }
}
