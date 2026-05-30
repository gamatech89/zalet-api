<?php

namespace App\Services;

use App\Models\Media;
use App\Models\MediaPurchase;
use App\Models\Subscription;
use App\Models\User;

class ContentAccessService
{
    /**
     * Check if a user can access the given media content.
     *
     * Access rules (in order):
     * 1. Free content → always accessible
     * 2. Owner → always accessible
     * 3. PPV purchased → accessible regardless of subscription
     * 4. Plan-locked (premium/vip) → user's subscription level >= required level
     * 5. PPV not purchased → must purchase
     */
    public function canAccess(?User $user, Media $media): bool
    {
        // Free content (no subscription required, not PPV)
        if ($media->access_level === 'free' && !$media->is_ppv) {
            return true;
        }

        // Anonymous users can only access free content
        if (!$user) {
            return false;
        }

        // Owner always has access
        if ($media->user_id === $user->id) {
            return true;
        }

        // Check single-video purchase (PPV)
        if ($media->is_ppv && $this->hasPurchased($user, $media)) {
            return true;
        }

        // Plan-locked content (premium, vip)
        if ($media->required_plan_level && $media->required_plan_level > 0) {
            if ($user->hasSubscriptionLevel($media->required_plan_level)) {
                return true;
            }
        }

        // Subscription-only content (legacy: access_level = 'premium' or 'vip')
        if (in_array($media->access_level, ['premium', 'vip'])) {
            $requiredLevel = $media->access_level === 'vip' ? 2 : 1;
            if ($user->hasSubscriptionLevel($requiredLevel)) {
                return true;
            }
        }

        // Free access_level but PPV-only (must purchase)
        if ($media->access_level === 'free' && $media->is_ppv) {
            return false;
        }

        return false;
    }

    /**
     * Get the access status for a user on a media item.
     * Returns structured data for the frontend to know what to display.
     */
    public function getAccessInfo(?User $user, Media $media): array
    {
        $canAccess = $this->canAccess($user, $media);
        $subscriptionLevel = $user ? $this->getUserSubscriptionLevel($user) : 0;

        return [
            'can_access' => $canAccess,
            'access_level' => $media->access_level,
            'required_plan_level' => $media->required_plan_level,
            'is_ppv' => $media->is_ppv,
            'ppv_price' => $media->is_ppv ? $media->price_coins : null,
            'ppv_purchased' => $user && $media->is_ppv ? $this->hasPurchased($user, $media) : false,
            'is_subscribed' => $subscriptionLevel > 0,
            'subscription_level' => $subscriptionLevel,
        ];
    }

    /**
     * Check if user has purchased specific media.
     */
    protected function hasPurchased(User $user, Media $media): bool
    {
        return MediaPurchase::where('user_id', $user->id)
            ->where('media_id', $media->id)
            ->exists();
    }

    /**
     * Get user's current subscription level.
     */
    protected function getUserSubscriptionLevel(User $user): int
    {
        $subscription = Subscription::where('user_id', $user->id)
            ->active()
            ->with('plan')
            ->first();

        return $subscription?->plan?->level ?? 0;
    }
}
