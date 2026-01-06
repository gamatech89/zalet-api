<?php

declare(strict_types=1);

namespace App\Domains\Duel\Resources;

use App\Domains\Duel\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Message
 */
final class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'content' => $this->content,
            'meta' => $this->meta,
            'createdAt' => $this->created_at->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'uuid' => $this->user->uuid,
                'username' => $this->user->profile?->username,
                'avatarUrl' => $this->user->profile?->avatar_url,
            ] : null),
        ];
    }
}
