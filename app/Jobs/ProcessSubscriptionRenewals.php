<?php

namespace App\Jobs;

use App\Services\SubscriptionRenewalService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessSubscriptionRenewals implements ShouldQueue
{
    use Queueable;

    public function handle(SubscriptionRenewalService $service): void
    {
        $service->processRenewals();
    }
}
