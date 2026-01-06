<?php

declare(strict_types=1);

namespace App\Domains\Duel\Resources;

use App\Domains\Duel\Models\ChatRoom;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChatRoom
 */
final class ChatRoomResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'slug' => $this->slug,
            'type' => $this->type->value,
            'typeLabel' => $this->type->label(),
            'maxParticipants' => $this->max_participants,
            'isActive' => $this->is_active,
            'meta' => $this->meta,
            'createdAt' => $this->created_at->toIso8601String(),
            'location' => $this->whenLoaded('location', fn () => $this->location ? [
                'id' => $this->location->id,
                'city' => $this->location->city,
                'country' => $this->location->country,
            ] : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'uuid' => $this->creator->uuid,
                'username' => $this->creator->profile?->username,
                'displayName' => $this->creator->profile?->display_name,
                'avatarUrl' => $this->creator->profile?->avatar_url,
            ] : null),
            'liveSession' => $this->whenLoaded('liveSession', fn () => new LiveSessionResource($this->liveSession)),
        ];
    }
}
