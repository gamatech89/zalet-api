<?php

declare(strict_types=1);

namespace App\Domains\Duel\Resources;

use App\Domains\Duel\Models\LiveSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LiveSession
 */
final class LiveSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'hostScore' => $this->host_score,
            'guestScore' => $this->guest_score,
            'scheduledAt' => $this->scheduled_at?->toIso8601String(),
            'startedAt' => $this->started_at?->toIso8601String(),
            'endedAt' => $this->ended_at?->toIso8601String(),
            'durationSeconds' => $this->duration_seconds,
            'meta' => $this->meta,
            'createdAt' => $this->created_at->toIso8601String(),
            'host' => $this->whenLoaded('host', fn () => [
                'id' => $this->host->id,
                'uuid' => $this->host->uuid,
                'username' => $this->host->profile?->username,
                'avatarUrl' => $this->host->profile?->avatar_url,
            ]),
            'guest' => $this->whenLoaded('guest', fn () => $this->guest ? [
                'id' => $this->guest->id,
                'uuid' => $this->guest->uuid,
                'username' => $this->guest->profile?->username,
                'avatarUrl' => $this->guest->profile?->avatar_url,
            ] : null),
            'winner' => $this->whenLoaded('winner', fn () => $this->winner ? [
                'id' => $this->winner->id,
                'uuid' => $this->winner->uuid,
                'username' => $this->winner->profile?->username,
            ] : null),
            'chatRoom' => $this->whenLoaded('chatRoom', fn () => $this->chatRoom ? [
                'id' => $this->chatRoom->id,
                'uuid' => $this->chatRoom->uuid,
                'name' => $this->chatRoom->name,
            ] : null),
        ];
    }
}
