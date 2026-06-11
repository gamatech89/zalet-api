<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Get suggested / featured users to follow.
     * GET /api/v1/users/suggested
     */
    public function suggested(Request $request): JsonResponse
    {
        $authUser = $request->user();

        $users = User::query()
            ->with('profile')
            ->when($authUser, fn ($q) => $q->where('id', '!=', $authUser->id))
            // Creators first, then by follower count desc, then recent
            ->orderByRaw("CASE WHEN role = 'creator' THEN 0 WHEN role = 'admin' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $currentUserId = $authUser?->id;

        return response()->json([
            'data' => $users->map(function ($user) use ($currentUserId) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'name' => $user->username,
                    'avatar_url' => $user->profile?->avatar_url,
                    'bio' => $user->profile?->bio,
                    'role' => $user->role,
                    'is_following' => $currentUserId
                        ? $user->followers()->where('follower_id', $currentUserId)->exists()
                        : false,
                    'type' => 'user',
                ];
            }),
        ]);
    }

    /**
     * Get public user profile by username or ID.
     * GET /api/v1/users/{user}
     */
    public function show(Request $request, string $key): JsonResponse
    {
        // Only query by ID if the key looks like a UUID; otherwise just by username
        $query = User::query();
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $key)) {
            $query->where('id', $key);
        }
        else {
            $query->where('username', $key);
        }
        $user = $query->with(['profile'])->firstOrFail();

        $authUser = $request->user('sanctum');

        // Stats
        $followersCount = $user->followers()->count();
        $followingCount = $user->following()->count();
        $momentsCount = $user->media()->where('type', 'moment')->count(); // Assuming 'moment' type exists

        // Relationship status (if authenticated)
        $isFollowing = $authUser ? $authUser->following()->where('following_id', $user->id)->exists() : false;
        $isFollowedBy = $authUser ? $authUser->followers()->where('follower_id', $user->id)->exists() : false;

        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'role' => $user->role,
            'avatar_url' => $user->profile->avatar_url,
            'bio' => $user->profile->bio,
            // Use profile fields if available, otherwise null
            'location' => $user->profile->current_city && $user->profile->current_country
            ? "{$user->profile->current_city}, {$user->profile->current_country}"
            : null,
            'website' => $user->profile->website ?? null,
            'joined_at' => $user->created_at->toIso8601String(),
            'stats' => [
                'followers_count' => $followersCount,
                'following_count' => $followingCount,
                'moments_count' => $momentsCount,
                'is_following' => $isFollowing,
                'is_followed_by' => $isFollowedBy,
            ]
        ]);
    }
}