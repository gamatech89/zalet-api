<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRenewed extends Notification
{
    public function __construct(protected Subscription $subscription) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pretplata obnovljena – Zalet')
            ->view('emails.subscription-renewed', [
                'subscription' => $this->subscription,
                'planName' => $this->subscription->plan->name,
                'endsAt' => $this->subscription->ends_at->format('d. m. Y.'),
            ]);
    }
}
