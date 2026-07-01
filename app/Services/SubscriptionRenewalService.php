<?php

namespace App\Services;

use App\Models\Subscription;
use App\Notifications\SubscriptionExpired;
use App\Notifications\SubscriptionManualRenewalRequired;
use App\Notifications\SubscriptionPaymentFailed;
use App\Notifications\SubscriptionRenewed;
use Illuminate\Support\Facades\Log;

class SubscriptionRenewalService
{
    const PAYBYTOKEN_LIMIT_RSD = 2400;
    const MAX_RETRY_ATTEMPTS = 3;
    const GRACE_PERIOD_DAYS = 3;

    public function __construct(
        protected RaiffeisenPaymentService $paymentService,
        protected GlobalSubscriptionService $subscriptionService,
    ) {}

    public function processRenewals(): void
    {
        $due = Subscription::query()
            ->whereIn('status', ['active', 'past_due'])
            ->where('auto_renew', true)
            ->whereNotNull('payment_method_id')
            ->where('next_billing_date', '<=', now()->toDateString())
            ->with(['user', 'plan', 'paymentMethod'])
            ->get();

        foreach ($due as $subscription) {
            $this->attemptRenewal($subscription);
        }
    }

    protected function attemptRenewal(Subscription $subscription): void
    {
        $amount = $this->subscriptionService->calculatePrice(
            $subscription->plan,
            $subscription->billing_cycle
        );

        // Yearly or over silent-charge limit → send manual payment link
        if ($subscription->billing_cycle === 'yearly' || $amount > self::PAYBYTOKEN_LIMIT_RSD) {
            $this->sendManualPaymentNotification($subscription, $amount);
            return;
        }

        try {
            $result = $this->paymentService->createSubscriptionTokenizedPayment(
                user: $subscription->user,
                paymentMethod: $subscription->paymentMethod,
                plan: $subscription->plan,
                billingCycle: $subscription->billing_cycle,
            );

            if (($result['tran_code'] ?? '') === '000') {
                // Payment approved synchronously — process renewal inline
                $this->subscriptionService->renew(
                    $subscription,
                    $result['order_id'],
                    $result['amount_rsd'],
                );
                $this->onRenewalSuccess($subscription->fresh());
            } else {
                $this->onRenewalFailure($subscription, 'TranCode: ' . ($result['tran_code'] ?? 'unknown'));
            }
        } catch (\Exception $e) {
            Log::error('Subscription renewal attempt failed', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'error' => $e->getMessage(),
            ]);
            $this->onRenewalFailure($subscription, $e->getMessage());
        }
    }

    protected function onRenewalSuccess(Subscription $subscription): void
    {
        $subscription->update([
            'renewal_attempts' => 0,
            'last_renewal_error' => null,
        ]);

        $subscription->user->notify(new SubscriptionRenewed($subscription));

        Log::info('Subscription renewed successfully', [
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'next_billing_date' => $subscription->next_billing_date,
        ]);
    }

    protected function onRenewalFailure(Subscription $subscription, string $error): void
    {
        $attempts = $subscription->renewal_attempts + 1;

        if ($attempts >= self::MAX_RETRY_ATTEMPTS) {
            $subscription->update([
                'status' => 'expired',
                'auto_renew' => false,
                'renewal_attempts' => $attempts,
                'last_renewal_error' => $error,
            ]);
            $subscription->user->update(['subscription_level' => 0]);
            $subscription->user->notify(new SubscriptionExpired($subscription));

            Log::warning('Subscription expired after max retries', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'attempts' => $attempts,
            ]);
        } else {
            $subscription->update([
                'status' => 'past_due',
                'renewal_attempts' => $attempts,
                'last_renewal_error' => $error,
                'next_billing_date' => now()->addDays(self::GRACE_PERIOD_DAYS)->toDateString(),
            ]);
            $subscription->user->notify(new SubscriptionPaymentFailed($subscription));

            Log::warning('Subscription payment failed, entering grace period', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'attempts' => $attempts,
                'next_retry' => now()->addDays(self::GRACE_PERIOD_DAYS)->toDateString(),
            ]);
        }
    }

    protected function sendManualPaymentNotification(Subscription $subscription, float $amount): void
    {
        try {
            $paymentData = $this->paymentService->createSubscriptionPayment(
                user: $subscription->user,
                plan: $subscription->plan,
                billingCycle: $subscription->billing_cycle,
            );

            $subscription->user->notify(
                new SubscriptionManualRenewalRequired($subscription, $paymentData['payment_url'])
            );

            // Don't spam daily — check again in 3 days
            $subscription->update([
                'next_billing_date' => now()->addDays(3)->toDateString(),
            ]);

            Log::info('Manual renewal notification sent', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'amount' => $amount,
                'billing_cycle' => $subscription->billing_cycle,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send manual renewal notification', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
