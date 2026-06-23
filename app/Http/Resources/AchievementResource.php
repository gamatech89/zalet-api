<?php

namespace App\Http\Resources;

use App\Support\JsonMapping\JsonTypeConverter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AchievementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'event_type' => $this->event_type,
            'aggregation' => JsonTypeConverter::toArray($this->aggregation),
            'is_enabled' => $this->is_enabled,
            'tiers' => AchievementTierResource::collection($this->whenLoaded('tiers')),
            'created_at' => $this->created_at,
        ];
    }
}
