<?php

namespace App\Observers;

use App\Jobs\ResolveAchievementsJob;
use App\Models\UserEvent;

class UserEventObserver
{
    public function created(UserEvent $event): void
    {
        ResolveAchievementsJob::dispatch($event);
    }
}
