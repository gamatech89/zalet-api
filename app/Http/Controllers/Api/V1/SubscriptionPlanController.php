<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    /**
     * List all active subscription plans (public).
     *
     * GET /api/v1/subscription-plans
     */
    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::active()
            ->where('level', '>', 0)
            ->ordered()
            ->get()
            ->map(fn ($plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'level' => $plan->level,
                'price_monthly' => $plan->price_monthly,
                'price_yearly' => $plan->price_yearly,
                'features' => $plan->features ?? [],
                'limits' => $this->effectiveLimits($plan),
                'sort_order' => $plan->sort_order,
            ]);

        return response()->json(['data' => $plans]);
    }

    /**
     * List ALL plans including free and inactive (admin).
     *
     * GET /api/v1/admin/subscription-plans
     */
    public function adminIndex(): JsonResponse
    {
        $plans = SubscriptionPlan::ordered()->get()->map(fn ($plan) => [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'description' => $plan->description,
            'level' => $plan->level,
            'price_monthly' => $plan->price_monthly,
            'price_yearly' => $plan->price_yearly,
            'features' => $plan->features ?? [],
            'limits' => $this->effectiveLimits($plan),
            'is_active' => $plan->is_active,
            'sort_order' => $plan->sort_order,
            'subscriber_count' => $plan->activeSubscriberCount(),
        ]);

        return response()->json(['data' => $plans]);
    }

    /**
     * Update a subscription plan (admin).
     *
     * PUT /api/v1/admin/subscription-plans/{plan}
     */
    public function adminUpdate(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        $data = $request->validate([
            'name'                         => 'sometimes|string|max:100',
            'description'                  => 'nullable|string|max:500',
            'price_monthly'                => 'sometimes|numeric|min:0',
            'price_yearly'                 => 'nullable|numeric|min:0',
            'features'                     => 'sometimes|array',
            'features.*'                   => 'string|max:200',
            'is_active'                    => 'sometimes|boolean',
            'sort_order'                   => 'sometimes|integer|min:0',
            'limits'                       => 'sometimes|array',
            'limits.max_moments'           => 'nullable|integer|min:0',
            'limits.max_moment_duration_seconds' => 'nullable|integer|min:1',
            'limits.max_groups'            => 'nullable|integer|min:0',
            'limits.can_create_group'      => 'nullable|boolean',
            'limits.max_community_posts_per_month' => 'nullable|integer|min:0',
            'limits.community_post_coin_cost' => 'nullable|integer|min:0',
            'limits.can_watch_premium'     => 'nullable|boolean',
            'limits.can_create_community'  => 'nullable|boolean',
            'limits.monthly_free_coins'    => 'nullable|integer|min:0',
        ]);

        if (isset($data['limits'])) {
            $data['limits'] = array_filter($data['limits'], fn ($v) => $v !== null);
        }

        $plan->update($data);

        return response()->json(['data' => [
            ...$plan->toArray(),
            'limits' => $this->effectiveLimits($plan),
            'subscriber_count' => $plan->activeSubscriberCount(),
        ]]);
    }

    private function effectiveLimits(SubscriptionPlan $plan): array
    {
        $configDefaults = config("plan_limits.{$plan->level}", []);
        $dbLimits = $plan->limits ?? [];
        return array_merge($configDefaults, array_filter($dbLimits, fn ($v) => $v !== null));
    }
}
