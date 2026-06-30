<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\ProcessSubscriptionRenewals;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Delete S3 attachment files from old messages every Sunday at 02:00.
// Group chats: attachments older than 90 days.
// DM chats: attachments older than 365 days.
// Message text is never deleted.
Schedule::command('chat:prune-attachments')->weekly()->sundays()->at('02:00');

// Process subscription renewals daily at 07:00 Belgrade time.
Schedule::job(ProcessSubscriptionRenewals::class)
    ->dailyAt('07:00')
    ->timezone('Europe/Belgrade')
    ->withoutOverlapping();
