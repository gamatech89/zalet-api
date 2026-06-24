<?php

namespace App\Services\Achievements;

use App\Models\AchievementTier;
use App\Models\UserEvent;

class AchievementPipeline
{
    public function process(UserEvent $event): void
    {
        $tiers = AchievementTier::unresolvedForUser($event->user)
            ->whereHas('achievement', fn ($q) => $q->where('event_type', $event->type))
            ->with('achievement')
            ->get();

        foreach ($tiers as $tier) {
            $value = $tier->achievement->aggregation->evaluate($event->user, $tier->achievement->event_type);

            $tier->awardTo($event->user, $value);
        }
    }
}
