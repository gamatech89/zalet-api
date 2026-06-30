<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpired extends Notification
{
    public function __construct(protected Subscription $subscription) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pretplata je istekla – Zalet')
            ->view('emails.subscription-expired', [
                'planName' => $this->subscription->plan->name,
            ]);
    }
}
