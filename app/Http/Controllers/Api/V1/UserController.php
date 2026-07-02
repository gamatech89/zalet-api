<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Get suggested / featured users to follow.
     * GET /api/v1/users/suggested
     */
    public function suggested(Request $request): JsonResponse
    {
        $authUser = $request->user();

        $blockedIds = $authUser
            ? Block::where('blocker_id', $authUser->id)->pluck('blocked_id')
                ->merge(Block::where('blocked_id', $authUser->id)->pluck('blocker_id'))
                ->unique()
            : collect();

        $users = User::query()
            ->with('profile')
            ->when($authUser, fn ($q) => $q->where('id', '!=', $authUser->id))
            ->when($blockedIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $blockedIds))
            // Creators first, then by follower count desc, then recent
            ->orderByRaw("CASE WHEN role = 'creator' THEN 0 WHEN role = 'admin' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $currentUserId = $authUser?->id;

        // Single query to check which of these users the current user already follows
        if ($currentUserId) {
            $users->load(['followers' => fn ($q) => $q->where('follower_id', $currentUserId)->select('users.id')]);
        }

        return response()->json([
            'data' => $users->map(function ($user) use ($currentUserId) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'name' => $user->username,
                    'avatar_url' => $user->profile?->avatar_url,
                    'bio' => $user->profile?->bio,
                    'role' => $user->role,
                    'subscription_level' => $user->subscription_level,
                    'is_following' => $currentUserId ? $user->followers->isNotEmpty() : false,
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
        } else {
            $query->where('username', $key);
        }
        $user = $query->with(['profile'])->firstOrFail();

        $authUser = $request->user('sanctum');

        // Block check — return 404 to not reveal the user exists
        if ($authUser && $authUser->id !== $user->id) {
            $isBlocked = Block::where(function ($q) use ($authUser, $user) {
                $q->where('blocker_id', $authUser->id)->where('blocked_id', $user->id);
            })->orWhere(function ($q) use ($authUser, $user) {
                $q->where('blocker_id', $user->id)->where('blocked_id', $authUser->id);
            })->exists();

            if ($isBlocked) {
                return response()->json(['message' => 'Not found.'], 404);
            }
        }

        // One query for all three counts
        $user->loadCount([
            'followers',
            'following',
            'media as moments_count' => fn ($q) => $q->where('type', 'moment'),
        ]);

        // One query for both follow directions
        $isFollowing = false;
        $isFollowedBy = false;
        if ($authUser) {
            $followRows = DB::table('follows')
                ->where(fn ($q) => $q->where('follower_id', $authUser->id)->where('following_id', $user->id))
                ->orWhere(fn ($q) => $q->where('follower_id', $user->id)->where('following_id', $authUser->id))
                ->get(['follower_id', 'following_id']);
            $isFollowing  = $followRows->contains(fn ($r) => $r->follower_id === $authUser->id && $r->following_id === $user->id);
            $isFollowedBy = $followRows->contains(fn ($r) => $r->follower_id === $user->id   && $r->following_id === $authUser->id);
        }

        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'role' => $user->role,
            'subscription_level' => $user->subscription_level,
            'is_legacy_founder' => $user->is_legacy_founder,
            'avatar_url' => $user->profile->avatar_url,
            'bio' => $user->profile->bio,
            'location' => $user->profile->current_city && $user->profile->current_country
                ? "{$user->profile->current_city}, {$user->profile->current_country}"
                : null,
            'website' => $user->profile->website ?? null,
            'joined_at' => $user->created_at->toIso8601String(),
            'stats' => [
                'followers_count' => $user->followers_count,
                'following_count' => $user->following_count,
                'moments_count'   => $user->moments_count,
                'is_following'    => $isFollowing,
                'is_followed_by'  => $isFollowedBy,
            ]
        ]);
    }

    /**
     * Top gifters for a creator across all stream sessions.
     * GET /api/v1/users/{user}/top-gifters?period=weekly|all  (public)
     */
    public function topGifters(Request $request, string $key): JsonResponse
    {
        $query = User::query();
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $key)) {
            $query->where('id', $key);
        } else {
            $query->where('username', $key);
        }
        $user = $query->firstOrFail();

        $wallet = $user->wallet;
        if (!$wallet) {
            return response()->json(['data' => []]);
        }

        $period = $request->query('period', 'weekly') === 'all' ? 'all' : 'weekly';

        $q = Transaction::query()
            ->where('to_wallet_id', $wallet->id)
            ->where('type', 'tip')
            ->whereNotNull('gift_id')
            ->whereNotNull('stream_session_id');

        if ($period === 'weekly') {
            $q->where('transactions.created_at', '>=', now()->subDays(7));
        }

        $rows = $q
            ->join('wallets as w', 'w.id', '=', 'transactions.from_wallet_id')
            ->join('users as u', 'u.id', '=', 'w.user_id')
            ->leftJoin('profiles as p', 'p.user_id', '=', 'u.id')
            ->groupBy('u.id', 'u.username', 'p.avatar_url')
            ->orderByRaw('SUM(transactions.amount) DESC')
            ->limit(10)
            ->get([
                DB::raw('u.id as user_id'),
                'u.username',
                'p.avatar_url',
                DB::raw('SUM(transactions.amount) as total_coins'),
            ]);

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'user_id'     => $r->user_id,
                'username'    => $r->username,
                'avatar_url'  => $r->avatar_url,
                'total_coins' => (float) $r->total_coins,
            ]),
        ]);
    }
}
