<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Duel\Enums\LiveSessionStatus;
use App\Domains\Duel\Models\ChatRoom;
use App\Domains\Duel\Models\LiveSession;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LiveSession>
 */
final class LiveSessionFactory extends Factory
{
    protected $model = LiveSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'chat_room_id' => ChatRoom::factory(),
            'host_id' => User::factory(),
            'guest_id' => null,
            'status' => LiveSessionStatus::WAITING,
            'host_score' => 0,
            'guest_score' => 0,
            'started_at' => null,
            'ended_at' => null,
            'winner_id' => null,
            'meta' => [],
        ];
    }

    /**
     * Set the host user.
     */
    public function hostedBy(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'host_id' => $user->id,
        ]);
    }

    /**
     * Set the guest user.
     */
    public function withGuest(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'guest_id' => $user->id,
        ]);
    }

    /**
     * Set as waiting for guest.
     */
    public function waiting(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LiveSessionStatus::WAITING,
            'started_at' => null,
            'ended_at' => null,
        ]);
    }

    /**
     * Set as active duel.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LiveSessionStatus::ACTIVE,
            'guest_id' => $attributes['guest_id'] ?? User::factory(),
            'started_at' => now(),
            'ended_at' => null,
        ]);
    }

    /**
     * Set as paused.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LiveSessionStatus::PAUSED,
            'started_at' => $attributes['started_at'] ?? now()->subMinutes(10),
        ]);
    }

    /**
     * Set as completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LiveSessionStatus::COMPLETED,
            'started_at' => $attributes['started_at'] ?? now()->subMinutes(30),
            'ended_at' => now(),
        ]);
    }

    /**
     * Set as cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LiveSessionStatus::CANCELLED,
            'ended_at' => now(),
        ]);
    }

    /**
     * Set scores.
     */
    public function withScores(int $hostScore, int $guestScore): static
    {
        return $this->state(fn (array $attributes): array => [
            'host_score' => $hostScore,
            'guest_score' => $guestScore,
        ]);
    }

    /**
     * Set winner.
     */
    public function wonBy(User $winner): static
    {
        return $this->state(fn (array $attributes): array => [
            'winner_id' => $winner->id,
            'status' => LiveSessionStatus::COMPLETED,
            'ended_at' => now(),
        ]);
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
}
