<?php

declare(strict_types=1);

namespace App\Domains\Streaming\Resources;

use App\Domains\Identity\Resources\UserResource;
use App\Domains\Shared\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin \App\Domains\Streaming\Models\Post
 */
final class PostResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'type' => $this->type->value,
            'title' => $this->title,
            'description' => $this->description,
            'sourceUrl' => $this->source_url,
            'provider' => $this->provider?->value,
            'providerId' => $this->provider_id,
            'thumbnailUrl' => $this->thumbnail_url,
            'embedUrl' => $this->getEmbedUrl(),
            'durationSeconds' => $this->duration_seconds,
            'formattedDuration' => $this->getFormattedDuration(),
            'isPremium' => $this->is_premium,
            'isPublished' => $this->is_published,
            'publishedAt' => $this->published_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}
