<?php

declare(strict_types=1);

namespace App\Domains\Duel\Resources;

use App\Domains\Duel\Models\DuelEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DuelEvent
 */
final class DuelEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'eventType' => $this->event_type->value,
            'payload' => $this->payload,
            'createdAt' => $this->created_at->toIso8601String(),
            'actor' => $this->whenLoaded('actor', fn () => $this->actor ? [
                'id' => $this->actor->id,
                'uuid' => $this->actor->uuid,
                'username' => $this->actor->profile?->username,
            ] : null),
            'target' => $this->whenLoaded('target', fn () => $this->target ? [
                'id' => $this->target->id,
                'uuid' => $this->target->uuid,
                'username' => $this->target->profile?->username,
            ] : null),
        ];
    }
}
