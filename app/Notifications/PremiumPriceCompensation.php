<?php

namespace App\Notifications;

use App\Models\AppSetting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PremiumPriceCompensation extends Notification
{
    public function __construct(protected int $coins) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Poklon za tebe – Zalet snižava Premium na 750 RSD')
            ->view('emails.premium-price-compensation', [
                'username' => $notifiable->username,
                'coins'    => $this->coins,
                'company'  => [
                    'name'    => AppSetting::get('company_name', 'Zalet d.o.o.'),
                    'email'   => AppSetting::get('company_email', 'info@zaletyu.com'),
                ],
            ]);
    }
}
