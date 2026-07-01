<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiryReminder extends Notification
{
    public function __construct(
        protected Subscription $subscription,
        protected int $daysLeft,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sub  = $this->subscription;
        $plan = $sub->plan;

        return (new MailMessage)
            ->subject("Pretplata ističe za {$this->daysLeft} " . ($this->daysLeft === 1 ? 'dan' : 'dana') . ' – Zalet')
            ->view('emails.subscription-expiry-reminder', [
                'subscription' => $sub,
                'planName'     => $plan->name,
                'endsAt'       => $sub->ends_at->format('d. m. Y.'),
                'daysLeft'     => $this->daysLeft,
                'renewalUrl'   => 'https://zaletyu.com/subscriptions',
            ]);
    }
}
