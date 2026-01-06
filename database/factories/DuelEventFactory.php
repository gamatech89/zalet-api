<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Duel\Enums\DuelEventType;
use App\Domains\Duel\Models\DuelEvent;
use App\Domains\Duel\Models\LiveSession;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DuelEvent>
 */
final class DuelEventFactory extends Factory
{
    protected $model = DuelEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'live_session_id' => LiveSession::factory(),
            'actor_id' => User::factory(),
            'target_id' => null,
            'event_type' => DuelEventType::USER_JOINED,
            'payload' => [],
        ];
    }

    /**
     * Set the live session.
     */
    public function forSession(LiveSession $session): static
    {
        return $this->state(fn (array $attributes): array => [
            'live_session_id' => $session->id,
        ]);
    }

    /**
     * Set the actor user.
     */
    public function byActor(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'actor_id' => $user->id,
        ]);
    }

    /**
     * Set the target user.
     */
    public function targetUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'target_id' => $user->id,
        ]);
    }

    /**
     * Gift sent event.
     */
    public function giftSent(string $giftSlug, int $creditValue): static
    {
        return $this->state(fn (array $attributes): array => [
            'event_type' => DuelEventType::GIFT_SENT,
            'payload' => [
                'gift_slug' => $giftSlug,
                'credit_value' => $creditValue,
            ],
        ]);
    }

    /**
     * User joined event.
     */
    public function userJoined(): static
    {
        return $this->state(fn (array $attributes): array => [
            'event_type' => DuelEventType::USER_JOINED,
            'payload' => [],
        ]);
    }

    /**
     * User left event.
     */
    public function userLeft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'event_type' => DuelEventType::USER_LEFT,
            'payload' => [],
        ]);
    }

    /**
     * Score updated event.
     */
    public function scoreUpdated(int $hostScore, int $guestScore): static
    {
        return $this->state(fn (array $attributes): array => [
            'event_type' => DuelEventType::SCORE_UPDATED,
            'payload' => [
                'host_score' => $hostScore,
                'guest_score' => $guestScore,
            ],
        ]);
    }

    /**
     * Round ended event.
     */
    public function roundEnded(int $roundNumber, ?string $winnerId = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'event_type' => DuelEventType::ROUND_ENDED,
            'payload' => [
                'round_number' => $roundNumber,
                'winner_id' => $winnerId,
            ],
        ]);
    }

    /**
     * Duel ended event.
     */
    public function duelEnded(?string $winnerId = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'event_type' => DuelEventType::DUEL_ENDED,
            'payload' => [
                'winner_id' => $winnerId,
            ],
        ]);
    }

    /**
     * Viewer comment event.
     */
    public function viewerComment(string $message): static
    {
        return $this->state(fn (array $attributes): array => [
            'event_type' => DuelEventType::VIEWER_COMMENT,
            'payload' => [
                'message' => $message,
            ],
        ]);
    }
}
