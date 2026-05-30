<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\StreamSession;
use App\Models\Subscription;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreatorController extends Controller
{
    /**
     * Get creator dashboard overview stats.
     * GET /api/v1/creator/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $walletId = $user->wallet?->id;

        // Total earnings (all completed transactions to this wallet)
        $totalEarnings = $walletId ? Transaction::where('to_wallet_id', $walletId)
            ->completed()
            ->whereIn('type', ['tip', 'ppv', 'subscription'])
            ->sum('amount') : 0;

        // This month's earnings
        $monthlyEarnings = $walletId ? Transaction::where('to_wallet_id', $walletId)
            ->completed()
            ->whereIn('type', ['tip', 'ppv', 'subscription'])
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount') : 0;

        // Subscriber counts (per-creator subscriptions deferred — returning 0 for now)
        $activeSubscribers = 0;

        $totalSubscribers = 0;

        // Content counts
        $contentCount = Media::where('user_id', $user->id)->count();
        $momentsCount = Media::where('user_id', $user->id)->where('type', 'moment')->count();
        $cinemaCount = Media::where('user_id', $user->id)->where('type', 'embed')->count();

        // Follower count
        $followersCount = $user->followers()->count();

        // Stream stats
        $totalStreams = $user->liveStreams()->count();
        $totalStreamEarnings = StreamSession::whereHas('liveStream', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->sum('total_coins_collected');

        return response()->json([
            'data' => [
                'earnings' => [
                    'total' => (float) $totalEarnings,
                    'this_month' => (float) $monthlyEarnings,
                ],
                'subscribers' => [
                    'active' => $activeSubscribers,
                    'total' => $totalSubscribers,
                ],
                'followers' => $followersCount,
                'content' => [
                    'total' => $contentCount,
                    'moments' => $momentsCount,
                    'cinema' => $cinemaCount,
                ],
                'streams' => [
                    'total' => $totalStreams,
                    'total_earnings' => (float) $totalStreamEarnings,
                ],
            ],
        ]);
    }

    /**
     * Get detailed earnings breakdown.
     * GET /api/v1/creator/earnings
     */
    public function earnings(Request $request): JsonResponse
    {
        $user = $request->user();
        $walletId = $user->wallet?->id;

        if (!$walletId) {
            return response()->json([
                'data' => [
                    'tips' => 0,
                    'ppv' => 0,
                    'subscriptions' => 0,
                    'total' => 0,
                    'transactions' => [],
                ],
            ]);
        }

        $query = Transaction::where('to_wallet_id', $walletId)
            ->completed()
            ->whereIn('type', ['tip', 'ppv', 'subscription']);

        // Date filters
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Calculate totals by type
        $totals = (clone $query)->select('type', DB::raw('SUM(amount) as total'))
            ->groupBy('type')
            ->pluck('total', 'type')
            ->toArray();

        // Get recent transactions
        $transactions = $query->with(['fromWallet.user:id,username', 'gift', 'media'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => [
                'tips' => (float) ($totals['tip'] ?? 0),
                'ppv' => (float) ($totals['ppv'] ?? 0),
                'subscriptions' => (float) ($totals['subscription'] ?? 0),
                'total' => (float) array_sum($totals),
                'transactions' => $transactions->items(),
            ],
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    /**
     * List active subscribers.
     * GET /api/v1/creator/subscribers
     *
     * Note: Per-creator tiers not yet implemented. Returns all users with an
     * active platform subscription so creators can see their paying audience.
     */
    public function subscribers(Request $request): JsonResponse
    {
        $subscriptions = Subscription::active()
            ->with([
                'user:id,username',
                'user.profile:user_id,avatar_url',
                'plan:id,name,slug',
            ])
            ->orderBy('starts_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $subscriptions->map(function ($sub) {
                return [
                    'id'           => $sub->id,
                    'user'         => [
                        'id'         => $sub->user?->id,
                        'username'   => $sub->user?->username,
                        'avatar_url' => $sub->user?->profile?->avatar_url,
                    ],
                    'plan'         => $sub->plan?->name ?? 'Premium',
                    'plan_slug'    => $sub->plan?->slug ?? 'premium',
                    'subscribed_at'=> $sub->starts_at?->toIso8601String(),
                    'ends_at'      => $sub->ends_at?->toIso8601String(),
                ];
            }),
            'meta' => [
                'current_page' => $subscriptions->currentPage(),
                'last_page'    => $subscriptions->lastPage(),
                'per_page'     => $subscriptions->perPage(),
                'total'        => $subscriptions->total(),
            ],
        ]);
    }

    /**
     * List creator's content with stats.
     * GET /api/v1/creator/content
     */
    public function content(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Media::where('user_id', $user->id);

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $media = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        // Get purchase counts and earnings per media
        $mediaIds = $media->pluck('id')->toArray();
        $purchaseStats = Transaction::whereIn('media_id', $mediaIds)
            ->completed()
            ->select('media_id', DB::raw('COUNT(*) as purchase_count'), DB::raw('SUM(amount) as total_earnings'))
            ->groupBy('media_id')
            ->get()
            ->keyBy('media_id');

        return response()->json([
            'data' => $media->map(function ($item) use ($purchaseStats) {
                $stats = $purchaseStats->get($item->id);
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'type' => $item->type,
                    'is_ppv' => $item->is_ppv,
                    'price_coins' => (float) $item->price_coins,
                    'created_at' => $item->created_at->toIso8601String(),
                    'stats' => [
                        'purchases' => $stats?->purchase_count ?? 0,
                        'earnings' => (float) ($stats?->total_earnings ?? 0),
                    ],
                ];
            }),
            'meta' => [
                'current_page' => $media->currentPage(),
                'last_page' => $media->lastPage(),
                'per_page' => $media->perPage(),
                'total' => $media->total(),
            ],
        ]);
    }

    /**
     * Get past stream sessions with stats.
     * GET /api/v1/creator/streams/history
     */
    public function streamHistory(Request $request): JsonResponse
    {
        $user = $request->user();

        $sessions = StreamSession::whereHas('liveStream', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
            ->with(['liveStream:id,title'])
            ->whereNotNull('end_time')
            ->orderBy('start_time', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $sessions->map(function ($session) {
                return [
                    'id' => $session->id,
                    'title' => $session->liveStream?->title,
                    'start_time' => $session->start_time->toIso8601String(),
                    'end_time' => $session->end_time->toIso8601String(),
                    'duration_minutes' => $session->getDurationMinutes(),
                    'peak_viewers' => $session->peak_viewers,
                    'coins_collected' => (float) $session->total_coins_collected,
                ];
            }),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }

    /**
     * Get analytics data for charts.
     * GET /api/v1/creator/analytics
     */
    public function analytics(Request $request): JsonResponse
    {
        $user = $request->user();
        $walletId = $user->wallet?->id;
        $days = $request->get('days', 30);

        $startDate = now()->subDays($days)->startOfDay();

        // Daily earnings
        $dailyEarnings = $walletId ? Transaction::where('to_wallet_id', $walletId)
            ->completed()
            ->whereIn('type', ['tip', 'ppv', 'subscription'])
            ->where('created_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date')
            ->toArray() : [];

        // Daily new platform subscribers (per-creator tiers not yet implemented;
        // creator_id was dropped in the 2026-03-15 subscription refactor migration)
        $dailySubscribers = Subscription::active()
            ->where('starts_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(starts_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date')
            ->toArray();

        // Daily new followers
        $dailyFollowers = $user->followers()
            ->where('follows.created_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(follows.created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date')
            ->toArray();

        // Build complete date series
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $series[] = [
                'date' => $date,
                'earnings' => (float) ($dailyEarnings[$date]['total'] ?? 0),
                'new_subscribers' => (int) ($dailySubscribers[$date]['count'] ?? 0),
                'new_followers' => (int) ($dailyFollowers[$date]['count'] ?? 0),
            ];
        }

        return response()->json([
            'data' => [
                'period_days' => $days,
                'series' => $series,
            ],
        ]);
    }

    /**
     * Get top supporters (gifters).
     * GET /api/v1/creator/top-supporters
     */
    public function topSupporters(Request $request): JsonResponse
    {
        $user = $request->user();
        $walletId = $user->wallet?->id;
        $limit = $request->get('limit', 10);

        if (!$walletId) {
            return response()->json(['data' => []]);
        }

        $supporters = Transaction::where('to_wallet_id', $walletId)
            ->completed()
            ->whereIn('type', ['tip', 'ppv', 'subscription'])
            ->whereNotNull('from_wallet_id')
            ->select(
                'from_wallet_id',
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('COUNT(*) as transaction_count')
            )
            ->groupBy('from_wallet_id')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->with(['fromWallet.user:id,username', 'fromWallet.user.profile:user_id,avatar_url'])
            ->get();

        return response()->json([
            'data' => $supporters->map(function ($item) {
                return [
                    'user' => [
                        'id' => $item->fromWallet?->user?->id,
                        'username' => $item->fromWallet?->user?->username,
                        'avatar_url' => $item->fromWallet?->user?->profile?->avatar_url,
                    ],
                    'total_amount' => (float) $item->total_amount,
                    'transaction_count' => $item->transaction_count,
                ];
            }),
        ]);
    }
}
