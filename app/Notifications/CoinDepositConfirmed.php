<?php

namespace App\Notifications;

use App\Models\Transaction;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CoinDepositConfirmed extends Notification
{
    public function __construct(
        protected Transaction $transaction,
        protected float $amountRsd,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kupovina kovanica uspešna – Zalet')
            ->view('emails.coin-deposit-confirmed', [
                'coins'     => $this->transaction->amount,
                'amountRsd' => number_format($this->amountRsd, 2, ',', '.'),
                'orderId'   => $this->transaction->raiffeisen_order_id,
                'date'      => $this->transaction->updated_at->format('d. m. Y. H:i'),
            ]);
    }
}
