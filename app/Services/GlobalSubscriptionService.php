<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GlobalSubscriptionService
{
    public function __construct(private CoinService $coinService) {}

    /**
     * Create a subscription after payment is confirmed.
     */
    public function subscribe(
        User $user,
        SubscriptionPlan $plan,
        string $billingCycle,
        string $raiffeisenOrderId,
        float $pricePaid
    ): Subscription {
        // Check for existing active subscription
        $existingSubscription = Subscription::where('user_id', $user->id)
            ->active()
            ->first();

        if ($existingSubscription) {
            throw new \RuntimeException('You already have an active subscription.');
        }

        if (!$plan->is_active) {
            throw new \InvalidArgumentException('This subscription plan is not currently available.');
        }

        $duration = $billingCycle === 'yearly' ? 365 : 30;

        return DB::transaction(function () use ($user, $plan, $billingCycle, $raiffeisenOrderId, $pricePaid, $duration) {
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'billing_cycle' => $billingCycle,
                'price_paid' => $pricePaid,
                'starts_at' => now(),
                'ends_at' => now()->addDays($duration),
                'status' => 'active',
                'auto_renew' => true,
                'raiffeisen_order_id' => $raiffeisenOrderId,
                'next_billing_date' => $billingCycle === 'monthly'
                    ? now()->addMonth()->toDateString()
                    : now()->addYear()->toDateString(),
            ]);
            $user->update(['subscription_level' => $plan->level]);

            $bonusCoins = $plan->limits['monthly_free_coins'] ?? 0;
            if ($bonusCoins > 0) {
                $this->coinService->credit($user, (float) $bonusCoins, 'Subscription bonus coins');
            }

            return $subscription;
        });
    }

    /**
     * Upgrade an active subscription to a higher plan.
     * Immediately cancels the old subscription and creates a new one.
     */
    public function upgrade(
        Subscription $oldSubscription,
        SubscriptionPlan $newPlan,
        string $billingCycle,
        string $raiffeisenOrderId,
        float $pricePaid
    ): Subscription {
        $duration = $billingCycle === 'yearly' ? 365 : 30;
        $user = $oldSubscription->user;

        return DB::transaction(function () use ($oldSubscription, $newPlan, $billingCycle, $raiffeisenOrderId, $pricePaid, $duration, $user) {
            $oldSubscription->update([
                'status'       => 'cancelled',
                'auto_renew'   => false,
                'cancelled_at' => now(),
            ]);

            $subscription = Subscription::create([
                'user_id'              => $user->id,
                'subscription_plan_id' => $newPlan->id,
                'billing_cycle'        => $billingCycle,
                'price_paid'           => $pricePaid,
                'starts_at'            => now(),
                'ends_at'              => now()->addDays($duration),
                'status'               => 'active',
                'auto_renew'           => true,
                'raiffeisen_order_id'  => $raiffeisenOrderId,
                'next_billing_date'    => $billingCycle === 'monthly'
                    ? now()->addMonth()->toDateString()
                    : now()->addYear()->toDateString(),
            ]);
            $user->update(['subscription_level' => $newPlan->level]);

            $bonusCoins = $newPlan->limits['monthly_free_coins'] ?? 0;
            if ($bonusCoins > 0) {
                $this->coinService->credit($user, (float) $bonusCoins, 'Subscription bonus coins');
            }

            return $subscription;
        });
    }

    /**
     * Cancel a subscription (keeps access until ends_at, just disables auto-renew).
     */
    public function cancel(Subscription $subscription): Subscription
    {
        if (!$subscription->isActive()) {
            throw new \RuntimeException('Subscription is not active.');
        }

        $subscription->update([
            'status' => 'cancelled',
            'auto_renew' => false,
            'cancelled_at' => now(),
        ]);

        return $subscription->fresh();
    }

    /**
     * Renew an existing subscription after payment is confirmed.
     */
    public function renew(
        Subscription $subscription,
        string $raiffeisenOrderId,
        float $pricePaid
    ): Subscription {
        $plan = $subscription->plan;
        $duration = $subscription->billing_cycle === 'yearly' ? 365 : 30;

        $renewed = DB::transaction(function () use ($subscription, $raiffeisenOrderId, $pricePaid, $duration) {
            $subscription->update([
                'starts_at' => now(),
                'ends_at' => now()->addDays($duration),
                'status' => 'active',
                'auto_renew' => true,
                'price_paid' => $pricePaid,
                'raiffeisen_order_id' => $raiffeisenOrderId,
                'cancelled_at' => null,
                'next_billing_date' => $subscription->billing_cycle === 'monthly'
                    ? now()->addMonth()->toDateString()
                    : now()->addYear()->toDateString(),
            ]);

            return $subscription->fresh();
        });

        $bonusCoins = $plan->limits['monthly_free_coins'] ?? 0;
        if ($bonusCoins > 0) {
            $this->coinService->credit($subscription->user, (float) $bonusCoins, 'Subscription renewal bonus coins');
        }

        return $renewed;
    }

    /**
     * Upgrade/downgrade: switch the user's subscription to a different plan.
     */
    public function changePlan(
        Subscription $subscription,
        SubscriptionPlan $newPlan,
        string $billingCycle,
        string $raiffeisenOrderId,
        float $pricePaid
    ): Subscription {
        return DB::transaction(function () use ($subscription, $newPlan, $billingCycle, $raiffeisenOrderId, $pricePaid) {
            // Cancel current subscription immediately
            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            $duration = $billingCycle === 'yearly' ? 365 : 30;

            $newSubscription = Subscription::create([
                'user_id' => $subscription->user_id,
                'subscription_plan_id' => $newPlan->id,
                'billing_cycle' => $billingCycle,
                'price_paid' => $pricePaid,
                'starts_at' => now(),
                'ends_at' => now()->addDays($duration),
                'status' => 'active',
                'auto_renew' => true,
                'raiffeisen_order_id' => $raiffeisenOrderId,
                'next_billing_date' => $billingCycle === 'monthly'
                    ? now()->addMonth()->toDateString()
                    : now()->addYear()->toDateString(),
            ]);
            $subscription->user->update(['subscription_level' => $newPlan->level]);

            $bonusCoins = $newPlan->limits['monthly_free_coins'] ?? 0;
            if ($bonusCoins > 0) {
                $this->coinService->credit($subscription->user, (float) $bonusCoins, 'Subscription bonus coins');
            }

            return $newSubscription;
        });
    }

    /**
     * Check and expire subscriptions that have passed their end date.
     * Should be called by a scheduled command.
     */
    public function checkAndExpire(): int
    {
        $subscriptions = Subscription::where('status', 'active')
            ->where('ends_at', '<', now())
            ->with('user')
            ->get();

        foreach ($subscriptions as $subscription) {
            $subscription->update(['status' => 'expired']);
            $subscription->user?->update(['subscription_level' => 0]);
        }

        return $subscriptions->count();
    }

    /**
     * Check if a user has an active subscription.
     */
    public function hasActiveSubscription(User $user): bool
    {
        return Subscription::where('user_id', $user->id)
            ->active()
            ->exists();
    }

    /**
     * Get the user's current subscription level (0 = none).
     */
    public function getUserSubscriptionLevel(User $user): int
    {
        $subscription = Subscription::where('user_id', $user->id)
            ->hasAccess()
            ->with('plan')
            ->first();

        if (!$subscription || !$subscription->plan) {
            return 0;
        }

        return $subscription->plan->level;
    }

    /**
     * Get all available plans for display.
     */
    public function getAvailablePlans()
    {
        return SubscriptionPlan::active()
            ->ordered()
            ->get();
    }

    /**
     * Get the user's current active subscription with plan details.
     */
    public function getUserSubscription(User $user): ?Subscription
    {
        return Subscription::where('user_id', $user->id)
            ->hasAccess()
            ->with('plan')
            ->first();
    }

    /**
     * Calculate the price for a plan + billing cycle.
     */
    public function calculatePrice(SubscriptionPlan $plan, string $billingCycle): float
    {
        if ($billingCycle === 'yearly' && $plan->price_yearly) {
            return (float) $plan->price_yearly;
        }

        return (float) $plan->price_monthly;
    }
}
