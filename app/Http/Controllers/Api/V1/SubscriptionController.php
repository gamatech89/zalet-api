<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
        $subscription = $this->subscriptionService->getUserSubscription($request->user());

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

    protected function formatSubscription($subscription): array
    {
        $subscription->load('plan');

        return [
            'id' => $subscription->id,
            'plan' => [
                'id' => $subscription->plan->id,
                'name' => $subscription->plan->name,
                'slug' => $subscription->plan->slug,
                'level' => $subscription->plan->level,
            ],
            'billing_cycle' => $subscription->billing_cycle,
            'price_paid' => $subscription->price_paid,
            'starts_at' => $subscription->starts_at->toIso8601String(),
            'ends_at' => $subscription->ends_at->toIso8601String(),
            'status' => $subscription->status,
            'auto_renew' => $subscription->auto_renew,
            'days_remaining' => max(0, now()->diffInDays($subscription->ends_at, false)),
        ];
    }
}
