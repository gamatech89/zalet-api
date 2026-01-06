<?php

declare(strict_types=1);

namespace App\Domains\Identity\Resources;

use App\Domains\Identity\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Notification
 */
final class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'typeLabel' => $this->type->label(),
            'icon' => $this->type->icon(),
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'actor' => $this->whenLoaded('actor', fn () => $this->actor ? [
                'uuid' => $this->actor->uuid,
                'username' => $this->actor->profile?->username,
                'displayName' => $this->actor->profile?->display_name,
                'avatarUrl' => $this->actor->profile?->avatar_url,
            ] : null),
            'notifiable' => $this->when(
                $this->notifiable_type !== null,
                fn () => [
                    'type' => class_basename((string) $this->notifiable_type),
                    'id' => $this->notifiable_id,
                ]
            ),
            'isRead' => $this->isRead(),
            'readAt' => $this->read_at?->toIso8601String(),
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
