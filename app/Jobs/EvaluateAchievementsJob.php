<?php

namespace App\Jobs;

use App\Enums\EventType;
use App\Services\AchievementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateAchievementsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $userId,
        public readonly EventType $eventType,
    ) {}

    public function handle(AchievementService $service): void
    {
        $service->evaluate($this->userId, $this->eventType);
    }
}