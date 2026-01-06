<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Wallet\Contracts\PaymentProviderInterface;
use App\Domains\Wallet\DTOs\RefundDTO;
use App\Domains\Wallet\Models\LedgerEntry;
use App\Domains\Wallet\Models\PaymentIntent;
use App\Domains\Wallet\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class RequestRefundAction
{
    public function __construct(
        private readonly PaymentProviderInterface $paymentProvider,
    ) {}

    /**
     * Request a refund for a completed payment.
     *
     * @param PaymentIntent $intent The payment intent to refund
     * @param User $user The user requesting the refund (must be owner or admin)
     * @param int|null $amountCents Partial refund amount (null = full refund)
     * @return PaymentIntent Updated payment intent
     * @throws \InvalidArgumentException If refund is not allowed
     * @throws \RuntimeException If refund fails
     */
    public function execute(
        PaymentIntent $intent,
        User $user,
        ?int $amountCents = null,
    ): PaymentIntent {
        // Verify ownership or admin
        if ($intent->user_id !== $user->id && !$user->isAdmin()) {
            throw new \InvalidArgumentException('You are not authorized to refund this payment');
        }

        // Verify intent is completed
        if (!$intent->isCompleted()) {
            throw new \InvalidArgumentException('Only completed payments can be refunded');
        }

        // Determine refund amount
        $refundAmount = $amountCents ?? $intent->amount_cents;
        if ($refundAmount > $intent->amount_cents) {
            throw new \InvalidArgumentException('Refund amount cannot exceed original payment');
        }

        // Calculate credits to deduct (proportional)
        $creditsToDeduct = (int) round(
            ($refundAmount / $intent->amount_cents) * $intent->credits_amount
        );

        return DB::transaction(function () use ($intent, $refundAmount, $creditsToDeduct): PaymentIntent {
            // Deduct credits from wallet
            $wallet = Wallet::where('user_id', $intent->user_id)->first();

            if ($wallet === null || !$wallet->canDebit($creditsToDeduct)) {
                throw new \RuntimeException('Insufficient credits for refund');
            }

            $wallet->debit(
                amount: $creditsToDeduct,
                type: LedgerEntry::TYPE_REFUND,
                referenceType: PaymentIntent::class,
                referenceId: $intent->id,
                description: "Refund: {$creditsToDeduct} credits",
                meta: [
                    'payment_intent_uuid' => $intent->uuid,
                    'refund_amount_cents' => $refundAmount,
                ],
            );

            // Call payment provider for refund
            $refundDto = new RefundDTO(
                orderIdentification: $intent->provider_order_id ?? '',
                transactionId: $intent->meta['transaction_id'] ?? '',
                amountCents: $refundAmount,
            );

            $refundResponse = $this->paymentProvider->issueRefund($refundDto);

            // Update intent status
            $intent->update([
                'status' => PaymentIntent::STATUS_REFUNDED,
                'meta' => array_merge($intent->meta, [
                    'refunded_at' => now()->toIso8601String(),
                    'refund_amount_cents' => $refundAmount,
                    'credits_deducted' => $creditsToDeduct,
                    'refund_response' => $refundResponse->toArray(),
                ]),
            ]);

            Log::info('Payment refunded', [
                'intent_id' => $intent->id,
                'user_id' => $intent->user_id,
                'refund_amount' => $refundAmount,
                'credits_deducted' => $creditsToDeduct,
            ]);

            $intent->refresh();

            return $intent;
        });
    }
}
