<?php

namespace App\Notifications;

use App\Models\AppSetting;
use App\Models\Subscription;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionConfirmed extends Notification
{
    public function __construct(protected Subscription $subscription) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sub  = $this->subscription;
        $plan = $sub->plan;

        $cycleLabel = $sub->billing_cycle === 'yearly' ? 'godišnja' : 'mesečna';
        $periodLabel = $sub->billing_cycle === 'yearly' ? '12 meseci' : '30 dana';

        return (new MailMessage)
            ->subject('Pretplata aktivirana – Zalet')
            ->view('emails.subscription-confirmed', [
                'subscription' => $sub,
                'planName'     => $plan->name,
                'cycleLabel'   => $cycleLabel,
                'periodLabel'  => $periodLabel,
                'startsAt'     => $sub->starts_at->format('d. m. Y.'),
                'endsAt'       => $sub->ends_at->format('d. m. Y.'),
                'pricePaid'    => number_format((float) $sub->price_paid, 2, ',', '.'),
                'orderId'      => $sub->raiffeisen_order_id,
                'company'      => [
                    'name'    => AppSetting::get('company_name', 'Zalet d.o.o.'),
                    'pib'     => AppSetting::get('company_pib', ''),
                    'mb'      => AppSetting::get('company_mb', ''),
                    'address' => AppSetting::get('company_address', ''),
                    'email'   => AppSetting::get('company_email', 'info@zaletyu.com'),
                    'account' => AppSetting::get('company_bank_account', ''),
                ],
            ]);
    }
}
