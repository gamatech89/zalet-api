<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\GlobalSubscriptionService;
use App\Services\RaiffeisenPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        protected GlobalSubscriptionService $subscriptionService,
        protected RaiffeisenPaymentService $paymentService
    ) {}

    /**
     * Initiate a subscription payment.
     *
     * POST /api/v1/subscriptions
     */
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => ['required', 'uuid', 'exists:subscription_plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
        ]);

        $user = $request->user();
        $plan = SubscriptionPlan::findOrFail($request->input('plan_id'));

        // Check if plan is active
        if (!$plan->is_active) {
            return response()->json([
                'message' => 'This plan is not currently available.',
            ], 400);
        }

        // Check for existing active subscription
        $existing = $this->subscriptionService->getUserSubscription($user);
        if ($existing) {
            return response()->json([
                'message' => 'You already have an active subscription.',
                'data' => $this->formatSubscription($existing),
            ], 409);
        }

        try {
            // Create Raiffeisen payment order
            $paymentData = $this->paymentService->createSubscriptionPayment(
                user: $user,
                plan: $plan,
                billingCycle: $request->input('billing_cycle')
            );

            return response()->json([
                'message' => 'Payment initiated. Complete the payment to activate your subscription.',
                'data' => $paymentData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get current user's active subscription.
     *
     * GET /api/v1/subscriptions/current
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();

        // Return active OR past_due subscriptions (past_due keeps access during grace period)
        $subscription = Subscription::where('user_id', $user->id)
            ->whereIn('status', ['active', 'past_due'])
            ->with(['plan', 'paymentMethod'])
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json([
                'data' => null,
                'message' => 'No active subscription.',
            ]);
        }

        return response()->json([
            'data' => $this->formatSubscription($subscription),
        ]);
    }

    /**
     * Cancel the current subscription.
     *
     * POST /api/v1/subscriptions/cancel
     */
    public function cancel(
        Request $request,
        GlobalSubscriptionService $subscriptionService,
    ): JsonResponse {
        $user = $request->user();
        $subscription = $user->subscriptions()->active()->first();

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription to cancel.',
            ], 404);
        }

        try {
            $subscription = $subscriptionService->cancel($subscription);

            return response()->json([
                'message' => 'Subscription cancelled. Access continues until ' . $subscription->ends_at->toDateString(),
                'data' => $this->formatSubscription($subscription),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Change the current subscription plan (upgrade/downgrade).
     *
     * POST /api/v1/subscriptions/change-plan
     */
    public function changePlan(
        Request $request,
        GlobalSubscriptionService $subscriptionService,
        RaiffeisenPaymentService $paymentService,
    ): JsonResponse {
        $request->validate([
            'plan_id' => ['required', 'exists:subscription_plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
        ]);

        $user = $request->user();
        $currentSubscription = $user->subscriptions()->active()->first();

        if (!$currentSubscription) {
            return response()->json([
                'message' => 'No active subscription. Please subscribe first.',
            ], 404);
        }

        $newPlan = \App\Models\SubscriptionPlan::findOrFail($request->plan_id);

        if ($currentSubscription->subscription_plan_id === $newPlan->id
            && $currentSubscription->billing_cycle === $request->billing_cycle) {
            return response()->json([
                'message' => 'You are already on this plan with this billing cycle.',
            ], 422);
        }

        try {
            // Create a payment order for the new plan
            $paymentData = $paymentService->createSubscriptionPayment(
                user: $user,
                plan: $newPlan,
                billingCycle: $request->billing_cycle,
            );

            return response()->json([
                'message' => 'Plan change payment initiated.',
                'data' => [
                    'payment_url' => $paymentData['payment_url'],
                    'order_id' => $paymentData['order_id'],
                    'new_plan' => [
                        'id' => $newPlan->id,
                        'name' => $newPlan->name,
                        'level' => $newPlan->level,
                    ],
                    'billing_cycle' => $request->billing_cycle,
                    'amount' => $paymentData['amount'],
                    'currency' => 'RSD',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to initiate plan change.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle auto-renewal and optionally set the payment method.
     *
     * POST /api/v1/subscriptions/auto-renew
     */
    public function toggleAutoRenew(Request $request): JsonResponse
    {
        $request->validate([
            'enabled' => ['required', 'boolean'],
            'payment_method_id' => ['nullable', 'uuid', 'exists:payment_methods,id'],
        ]);

        $user = $request->user();
        $subscription = $user->subscriptions()
            ->whereIn('status', ['active', 'past_due'])
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription found.',
            ], 404);
        }

        // Validate payment method belongs to user
        if ($request->filled('payment_method_id')) {
            $paymentMethod = $user->paymentMethods()->find($request->input('payment_method_id'));
            if (!$paymentMethod) {
                return response()->json([
                    'message' => 'Payment method not found or does not belong to you.',
                ], 422);
            }
        }

        $updateData = ['auto_renew' => $request->boolean('enabled')];

        if ($request->filled('payment_method_id')) {
            $updateData['payment_method_id'] = $request->input('payment_method_id');
        }

        $subscription->update($updateData);

        return response()->json([
            'message' => $request->boolean('enabled')
                ? 'Auto-renewal enabled.'
                : 'Auto-renewal disabled.',
            'data' => $this->formatSubscription($subscription->fresh()),
        ]);
    }

    /**
     * Update the renewal card for the current subscription.
     *
     * POST /api/v1/subscriptions/payment-method
     */
    public function setPaymentMethod(Request $request): JsonResponse
    {
        $request->validate([
            'payment_method_id' => ['required', 'uuid', 'exists:payment_methods,id'],
        ]);

        $user = $request->user();
        $subscription = $user->subscriptions()
            ->whereIn('status', ['active', 'past_due'])
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription found.',
            ], 404);
        }

        $paymentMethod = $user->paymentMethods()->find($request->input('payment_method_id'));
        if (!$paymentMethod) {
            return response()->json([
                'message' => 'Payment method not found or does not belong to you.',
            ], 422);
        }

        $subscription->update(['payment_method_id' => $paymentMethod->id]);

        return response()->json([
            'message' => 'Renewal payment method updated.',
            'data' => $this->formatSubscription($subscription->fresh()),
        ]);
    }

    /**
     * Upgrade current subscription to a higher plan with proration.
     *
     * POST /api/v1/subscriptions/upgrade
     */
    public function upgrade(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id'       => ['required', 'uuid', 'exists:subscription_plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
        ]);

        $user = $request->user();

        $currentSub = $user->subscriptions()
            ->whereIn('status', ['active', 'past_due'])
            ->with('plan')
            ->latest()
            ->first();

        if (!$currentSub) {
            return response()->json(['message' => 'No active subscription to upgrade.'], 404);
        }

        $newPlan = SubscriptionPlan::findOrFail($request->input('plan_id'));

        if (!$newPlan->is_active) {
            return response()->json(['message' => 'This plan is not currently available.'], 400);
        }

        $newPrice = $this->subscriptionService->calculatePrice($newPlan, $request->input('billing_cycle'));
        $currentPrice = $currentSub->price_paid;

        // Proration: credit for remaining days
        $daysTotal     = max(1, $currentSub->starts_at->diffInDays($currentSub->ends_at));
        $daysRemaining = max(0, (int) ceil(now()->floatDiffInDays($currentSub->ends_at)));
        $creditRsd     = ($daysRemaining / $daysTotal) * $currentPrice;

        // Ignore small credits (< 100 RSD) — not worth a separate redirect
        if ($creditRsd < 100) {
            $creditRsd = 0;
        }

        $chargeAmount = max(0, $newPrice - $creditRsd);

        // Downgrade guard: charge must be positive (upgrading, not downgrading)
        if ($chargeAmount <= 0) {
            return response()->json(['message' => 'Nije moguće nadograditi — novi plan nije skuplji od trenutnog.'], 422);
        }

        try {
            $paymentData = $this->paymentService->createUpgradePayment(
                user: $user,
                newPlan: $newPlan,
                billingCycle: $request->input('billing_cycle'),
                oldSubscription: $currentSub,
                chargeAmount: $chargeAmount,
            );

            return response()->json([
                'message' => 'Upgrade initiated. Complete payment to activate.',
                'data'    => array_merge($paymentData, [
                    'credit_rsd'    => round($creditRsd, 2),
                    'days_remaining' => $daysRemaining,
                ]),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    protected function formatSubscription($subscription): array
    {
        $subscription->loadMissing(['plan', 'paymentMethod']);

        $plan = $subscription->plan;
        $planPrice = $subscription->billing_cycle === 'monthly'
            ? ($plan->price_monthly ?? 0)
            : ($plan->price_yearly ?? 0);

        $canAutoRenew = $subscription->billing_cycle === 'monthly'
            && $planPrice <= 2400;

        return [
            'id' => $subscription->id,
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'level' => $plan->level,
            ],
            'billing_cycle' => $subscription->billing_cycle,
            'price_paid' => $subscription->price_paid,
            'starts_at' => $subscription->starts_at->toIso8601String(),
            'ends_at' => $subscription->ends_at->toIso8601String(),
            'status' => $subscription->status,
            'auto_renew' => $subscription->auto_renew,
            'days_remaining' => max(0, (int) now()->diffInDays($subscription->ends_at, false)),
            'next_billing_date' => $subscription->next_billing_date?->toDateString(),
            'renewal_attempts' => $subscription->renewal_attempts ?? 0,
            'last_renewal_error' => $subscription->last_renewal_error,
            'can_auto_renew' => $canAutoRenew,
            'renewal_mode' => $canAutoRenew ? 'automatic' : 'manual_link',
            'payment_method' => $subscription->paymentMethod ? [
                'id' => $subscription->paymentMethod->id,
                'brand' => $subscription->paymentMethod->card_brand,
                'last_four' => $subscription->paymentMethod->last_four,
            ] : null,
        ];
    }
}
