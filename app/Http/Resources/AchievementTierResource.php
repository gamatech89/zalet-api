<?php

namespace App\Http\Resources;

use App\Support\JsonMapping\JsonTypeConverter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AchievementTierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'level' => $this->level,
            'threshold' => $this->threshold,
            'icon' => $this->icon,
            'reward' => $this->reward ? JsonTypeConverter::toArray($this->reward) : null,
            'progress' => $this->whenPivotLoaded('user_achievement_tiers', fn () => $this->pivot->progress),
            'unlocked_at' => $this->whenPivotLoaded('user_achievement_tiers', fn () => $this->pivot->unlocked_at),
        ];
    }
}
