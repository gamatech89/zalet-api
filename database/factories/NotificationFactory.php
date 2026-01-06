<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Models\Notification;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Enums\NotificationType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Notification>
 */
final class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'user_id' => User::factory(),
            'type' => fake()->randomElement(NotificationType::cases()),
            'title' => fake()->sentence(4),
            'body' => fake()->optional()->sentence(),
            'data' => null,
            'actor_id' => null,
            'notifiable_type' => null,
            'notifiable_id' => null,
            'read_at' => null,
        ];
    }

    /**
     * Create a notification for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create a notification with a specific type.
     */
    public function ofType(NotificationType $type): static
    {
        return $this->state(fn (): array => [
            'type' => $type,
            'title' => $type->label(),
        ]);
    }

    /**
     * Create a notification from a specific actor.
     */
    public function fromActor(User $actor): static
    {
        return $this->state(fn (): array => [
            'actor_id' => $actor->id,
        ]);
    }

    /**
     * Alias for fromActor.
     */
    public function withActor(User $actor): static
    {
        return $this->fromActor($actor);
    }

    /**
     * Create a notification with custom data.
     *
     * @param array<string, mixed> $data
     */
    public function withData(array $data): static
    {
        return $this->state(fn (): array => [
            'data' => $data,
        ]);
    }

    /**
     * Create a notification related to a model.
     */
    public function forNotifiable(object $notifiable): static
    {
        return $this->state(fn (): array => [
            'notifiable_type' => $notifiable::class,
            'notifiable_id' => $notifiable->id,
        ]);
    }

    /**
     * Create a read notification.
     */
    public function read(): static
    {
        return $this->state(fn (): array => [
            'read_at' => now()->subMinutes(fake()->numberBetween(1, 60)),
        ]);
    }

    /**
     * Create an unread notification.
     */
    public function unread(): static
    {
        return $this->state(fn (): array => [
            'read_at' => null,
        ]);
    }

    /**
     * Create a follow request notification.
     */
    public function followRequest(User $actor): static
    {
        return $this->state(fn (): array => [
            'type' => NotificationType::FOLLOW_REQUEST,
            'title' => 'New Follow Request',
            'body' => ($actor->profile?->display_name ?? 'Someone') . ' wants to follow you',
            'actor_id' => $actor->id,
        ]);
    }

    /**
     * Create a gift received notification.
     */
    public function giftReceived(User $actor, string $giftType, int $credits): static
    {
        return $this->state(fn (): array => [
            'type' => NotificationType::GIFT_RECEIVED,
            'title' => 'Gift Received!',
            'body' => ($actor->profile?->display_name ?? 'Someone') . " sent you a {$giftType}",
            'actor_id' => $actor->id,
            'data' => [
                'gift_type' => $giftType,
                'credits' => $credits,
            ],
        ]);
    }

    /**
     * Create a duel invite notification.
     */
    public function duelInvite(User $actor): static
    {
        return $this->state(fn (): array => [
            'type' => NotificationType::DUEL_INVITE,
            'title' => 'Duel Invitation',
            'body' => ($actor->profile?->display_name ?? 'Someone') . ' invited you to a duel',
            'actor_id' => $actor->id,
        ]);
    }

    /**
     * Create a system announcement notification.
     */
    public function systemAnnouncement(string $title, string $body): static
    {
        return $this->state(fn (): array => [
            'type' => NotificationType::SYSTEM_ANNOUNCEMENT,
            'title' => $title,
            'body' => $body,
            'actor_id' => null,
        ]);
    }
}
