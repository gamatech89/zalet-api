<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\MediaPurchase;
use App\Models\User;

class MediaPolicy
{
    /**
     * Determine if user can view the media.
     * Free content is public, PPV requires purchase.
     */
    public function view(?User $user, Media $media): bool
    {
        // Free content is accessible to everyone
        if (!$media->is_ppv) {
            return true;
        }

        // PPV requires authentication
        if (!$user) {
            return false;
        }

        // Owner can always view
        if ($media->user_id === $user->id) {
            return true;
        }

        // Check if user purchased this content
        return MediaPurchase::where('user_id', $user->id)
            ->where('media_id', $media->id)
            ->exists();
    }

    /**
     * Determine if user can create media.
     */
    public function create(User $user): bool
    {
        // Any authenticated user can create media
        return true;
    }

    /**
     * Determine if user can update the media.
     */
    public function update(User $user, Media $media): bool
    {
        return $user->id === $media->user_id;
    }

    /**
     * Determine if user can delete the media.
     */
    public function delete(User $user, Media $media): bool
    {
        // Owner or admin can delete
        return $user->id === $media->user_id || $user->isAdmin();
    }
}
