<?php

namespace App\Services;

use App\Models\BoardPost;
use App\Models\Conversation;
use App\Models\Media;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;

class PlanLimitsService
{
    /**
     * Get the plan limits for a user based on their subscription.
     *
     * Priority: plan's `limits` JSON (admin-configured) → config defaults.
     * Free users (level 0, no plan) always use config defaults.
     */
    public function getLimits(User $user): array
    {
        $subscription = Subscription::where('user_id', $user->id)
            ->hasAccess()
            ->with('plan')
            ->first();

        $level = $subscription?->plan?->level ?? 0;
        $configDefaults = config("plan_limits.{$level}", config('plan_limits.0'));

        // Free users (no plan record) — use config
        if (!$subscription || !$subscription->plan) {
            return $configDefaults;
        }

        // Subscribed users — merge plan's DB limits over config defaults
        $dbLimits = $subscription->plan->limits ?? [];

        return array_merge($configDefaults, array_filter($dbLimits, fn ($v) => $v !== null));
    }

    /**
     * Get the user's subscription level (0 = free, 1 = premium, 2 = vip).
     */
    public function getUserLevel(User $user): int
    {
        $subscription = Subscription::where('user_id', $user->id)
            ->hasAccess()
            ->with('plan')
            ->first();

        return $subscription?->plan?->level ?? 0;
    }

    /**
     * Get the plan name based on level.
     */
    public function getPlanName(int $level): string
    {
        return match ($level) {
            1 => 'Premium',
            2 => 'VIP',
            default => 'Free',
        };
    }

    // ─── Moment Limits ───

    /**
     * Check if user can post a new moment.
     * Returns true or an error message string.
     */
    public function canPostMoment(User $user): bool|string
    {
        if ($user->isCreator()) {
            return true;
        }

        $limits = $this->getLimits($user);
        $maxMoments = $limits['max_moments'];

        if ($maxMoments === null) {
            return true; // unlimited
        }

        $currentCount = Media::where('user_id', $user->id)
            ->where('type', 'moment')
            ->count();

        if ($currentCount >= $maxMoments) {
            $level = $this->getUserLevel($user);
            $planName = $this->getPlanName($level);

            return "You've reached the maximum of {$maxMoments} moments on your {$planName} plan. Upgrade to post more.";
        }

        return true;
    }

    /**
     * Get max moment duration in seconds for the user.
     */
    public function getMaxMomentDuration(User $user): int
    {
        $limits = $this->getLimits($user);

        return $limits['max_moment_duration_seconds'] ?? 30;
    }

    // ─── Group Chat Limits ───

    /**
     * Check if user can join another group chat.
     * Returns true or an error message string.
     */
    public function canJoinGroup(User $user): bool|string
    {
        $limits = $this->getLimits($user);
        $maxGroups = $limits['max_groups'];

        if ($maxGroups === null) {
            return true; // unlimited
        }

        $currentGroupCount = $user->conversations()
            ->where('is_group', true)
            ->count();

        if ($currentGroupCount >= $maxGroups) {
            return "You've reached the maximum of {$maxGroups} group chats on your free plan. Upgrade to join more.";
        }

        return true;
    }

    // ─── Community Post Limits ───

    /**
     * Check if user can post to a community board.
     * Returns: ['allowed' => bool, 'reason' => string|null, 'coin_cost' => int]
     */
    public function canPostToCommunity(User $user): array
    {
        $limits = $this->getLimits($user);
        $maxPosts = $limits['max_community_posts_per_month'];
        $coinCost = $limits['community_post_coin_cost'] ?? 0;

        // Unlimited posts
        if ($maxPosts === null) {
            return ['allowed' => true, 'reason' => null, 'coin_cost' => 0];
        }

        // Zero free posts — can pay with coins
        if ($maxPosts === 0) {
            if ($coinCost > 0) {
                $wallet = $user->wallet;
                $balance = $wallet ? $wallet->balance : 0;

                if ($balance >= $coinCost) {
                    return [
                        'allowed' => true,
                        'reason' => null,
                        'coin_cost' => $coinCost,
                    ];
                }

                return [
                    'allowed' => false,
                    'reason' => "Free users need {$coinCost} ZaletCoins to post. Your balance: {$balance} ZC.",
                    'coin_cost' => $coinCost,
                ];
            }

            return [
                'allowed' => false,
                'reason' => 'Community posts are not available on the Free plan. Upgrade to Premium.',
                'coin_cost' => 0,
            ];
        }

        // Has a monthly limit — check usage
        $postsThisMonth = BoardPost::where('user_id', $user->id)
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->count();

        if ($postsThisMonth >= $maxPosts) {
            $level = $this->getUserLevel($user);
            $planName = $this->getPlanName($level);

            return [
                'allowed' => false,
                'reason' => "You've used all {$maxPosts} community posts this month on your {$planName} plan.",
                'coin_cost' => 0,
            ];
        }

        return ['allowed' => true, 'reason' => null, 'coin_cost' => 0];
    }

    /**
     * Check if user can create a new group chat.
     */
    public function canCreateGroup(User $user): bool|string
    {
        $limits = $this->getLimits($user);

        if (!($limits['can_create_group'] ?? false)) {
            $level = $this->getUserLevel($user);
            $planName = $this->getPlanName($level);

            return "Creating group chats is not available on your {$planName} plan. Upgrade to VIP.";
        }

        return true;
    }

    // ─── Community Creation ───

    /**
     * Check if user can create a community / board.
     */
    public function canCreateCommunity(User $user): bool
    {
        $limits = $this->getLimits($user);

        return $limits['can_create_community'] ?? false;
    }

    // ─── Usage Stats (for frontend) ───

    /**
     * Get current usage counts for the user.
     */
    public function getUserUsage(User $user): array
    {
        return [
            'moments_count' => Media::where('user_id', $user->id)
                ->where('type', 'moment')
                ->count(),
            'groups_count' => $user->conversations()
                ->where('is_group', true)
                ->count(),
            'posts_this_month' => BoardPost::where('user_id', $user->id)
                ->where('created_at', '>=', Carbon::now()->startOfMonth())
                ->count(),
        ];
    }

    /**
     * Get full plan info + usage for the API response.
     */
    public function getPlanInfo(User $user): array
    {
        $level = $this->getUserLevel($user);

        return [
            'level' => $level,
            'plan_name' => $this->getPlanName($level),
            'limits' => $this->getLimits($user),
            'usage' => $this->getUserUsage($user),
        ];
    }
}
