<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;

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
                'limits' => $plan->limits ?? [],
                'sort_order' => $plan->sort_order,
            ]);

        return response()->json(['data' => $plans]);
    }
}
