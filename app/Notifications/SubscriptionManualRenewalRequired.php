<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionManualRenewalRequired extends Notification
{
    public function __construct(
        protected Subscription $subscription,
        protected string $paymentUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Obnovi svoju pretplatu – Zalet')
            ->view('emails.subscription-manual-renewal', [
                'subscription' => $this->subscription,
                'planName' => $this->subscription->plan->name,
                'endsAt' => $this->subscription->ends_at->format('d. m. Y.'),
                'paymentUrl' => $this->paymentUrl,
            ]);
    }
}
