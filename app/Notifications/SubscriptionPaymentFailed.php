<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionPaymentFailed extends Notification
{
    public function __construct(protected Subscription $subscription) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Naplata pretplate nije uspjela – Zalet')
            ->view('emails.subscription-payment-failed', [
                'subscription' => $this->subscription,
                'planName' => $this->subscription->plan->name,
                'nextAttempt' => $this->subscription->next_billing_date?->format('d. m. Y.'),
                'error' => $this->subscription->last_renewal_error,
                'attemptsLeft' => max(0, 3 - $this->subscription->renewal_attempts),
            ]);
    }
}
