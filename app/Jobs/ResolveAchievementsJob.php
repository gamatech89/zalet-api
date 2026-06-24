<?php

namespace App\Jobs;

use App\Models\UserEvent;
use App\Services\Achievements\AchievementPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ResolveAchievementsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private UserEvent $event,
    ) {}

    public function handle(AchievementPipeline $pipeline): void
    {
        $pipeline->process($this->event);
    }
}
