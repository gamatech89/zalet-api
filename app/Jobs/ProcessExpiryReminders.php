<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Notifications\SubscriptionExpiryReminder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessExpiryReminders implements ShouldQueue
{
    use Queueable;

    // Send reminders at these intervals before expiry.
    const REMIND_DAYS = [7, 3];

    public function handle(): void
    {
        foreach (self::REMIND_DAYS as $days) {
            $targetDate = now()->addDays($days)->toDateString();

            $subscriptions = Subscription::query()
                ->where('status', 'active')
                ->whereDate('ends_at', $targetDate)
                ->with(['user', 'plan'])
                ->get();

            foreach ($subscriptions as $subscription) {
                try {
                    $subscription->user->notify(new SubscriptionExpiryReminder($subscription, $days));

                    Log::info('Expiry reminder sent', [
                        'subscription_id' => $subscription->id,
                        'user_id'         => $subscription->user_id,
                        'days_left'       => $days,
                        'ends_at'         => $subscription->ends_at->toDateString(),
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send expiry reminder', [
                        'subscription_id' => $subscription->id,
                        'error'           => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
