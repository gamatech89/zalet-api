<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Board;
use App\Models\LiveStream;
use App\Models\Media;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Get platform statistics.
     * GET /api/v1/admin/stats
     */
    public function stats(): JsonResponse
    {
        // 4 queries instead of 12 — one per table using conditional aggregation
        $userStats = User::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
            SUM(CASE WHEN role = 'creator' THEN 1 ELSE 0 END) as creators,
            SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as regular,
            SUM(CASE WHEN is_legacy_founder THEN 1 ELSE 0 END) as legacy_founders
        ")->first();

        $txStats = DB::table('transactions')->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_volume,
            SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END) as deposits,
            SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN amount ELSE 0 END) as withdrawals
        ")->first();

        $mediaStats = Media::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN type = 'moment' THEN 1 ELSE 0 END) as moments,
            SUM(CASE WHEN type = 'embed' THEN 1 ELSE 0 END) as cinema,
            SUM(CASE WHEN type = 'long_form' THEN 1 ELSE 0 END) as long_form
        ")->first();

        $streamStats = LiveStream::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN is_live THEN 1 ELSE 0 END) as currently_live
        ")->first();

        $stats = [
            'users' => [
                'total'           => (int) $userStats->total,
                'admins'          => (int) $userStats->admins,
                'creators'        => (int) $userStats->creators,
                'regular'         => (int) $userStats->regular,
                'legacy_founders' => (int) $userStats->legacy_founders,
            ],
            'transactions' => [
                'total'        => (int) $txStats->total,
                'total_volume' => (float) $txStats->total_volume,
                'deposits'     => (float) $txStats->deposits,
                'withdrawals'  => (float) $txStats->withdrawals,
            ],
            'content' => [
                'media_total' => (int) $mediaStats->total,
                'moments'     => (int) $mediaStats->moments,
                'cinema'      => (int) $mediaStats->cinema,
                'long_form'   => (int) $mediaStats->long_form,
            ],
            'streams' => [
                'total'         => (int) $streamStats->total,
                'currently_live'=> (int) $streamStats->currently_live,
            ],
        ];

        return response()->json(['data' => $stats]);
    }

    /**
     * List all users with filters and pagination.
     * GET /api/v1/admin/users
     */
    public function listUsers(Request $request): JsonResponse
    {
        $query = User::with([
            'profile',
            'wallet:id,user_id,balance',
            'subscriptions' => function ($q) {
                $q->active()
                  ->select(['id', 'user_id', 'subscription_plan_id', 'status', 'ends_at'])
                  ->with('plan:id,name');
            },
        ]);

        // Filter by role
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Filter by legacy founder status
        if ($request->has('is_legacy_founder')) {
            $query->where('is_legacy_founder', filter_var($request->is_legacy_founder, FILTER_VALIDATE_BOOLEAN));
        }

        // Search by email, username, or IP address (case-insensitive)
        if ($request->has('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(email) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(username) LIKE ?', ["%{$search}%"])
                  ->orWhere('last_ip', 'LIKE', "%{$search}%")
                  ->orWhere('registration_ip', 'LIKE', "%{$search}%");
            });
        }

        // Filter by subscription plan slug (free, premium, vip, etc.)
        if ($request->filled('plan')) {
            $query->whereHas('subscriptions', fn($q) => $q->active()
                ->whereHas('plan', fn($pq) => $pq->where('slug', $request->plan))
            );
        }

        // Filter by email verification status
        if ($request->has('verified')) {
            if ($request->verified === 'verified') {
                $query->whereNotNull('email_verified_at');
            } elseif ($request->verified === 'unverified') {
                $query->whereNull('email_verified_at');
            }
        }

        // Sorting
        $allowedSort = ['created_at', 'username', 'email', 'role', 'last_ip'];
        $sortBy = in_array($request->get('sort_by'), $allowedSort) ? $request->get('sort_by') : 'created_at';
        $sortOrder = $request->get('sort_order') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $users = $query->select([
            'id', 'username', 'email', 'role', 'is_active', 'is_legacy_founder',
            'last_ip', 'registration_ip', 'suspended_until', 'suspension_reason',
            'email_verified_at', 'created_at',
        ])->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Update user role or status.
     * PATCH /api/v1/admin/users/:id
     */
    public function updateUser(UpdateUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        // role is not mass-assignable; set it directly
        if (array_key_exists('role', $validated)) {
            $user->role = $validated['role'];
            unset($validated['role']);
        }

        $user->fill($validated)->save();

        return response()->json([
            'message' => 'User updated successfully.',
            'data' => $user->fresh(['profile', 'wallet']),
        ]);
    }

    /**
     * Delete a user and all their data.
     * DELETE /api/v1/admin/users/:id
     */
    public function deleteUser(User $user): JsonResponse
    {
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }

    /**
     * Temporarily suspend a user for a set number of hours.
     * POST /api/v1/admin/users/{user}/suspend
     */
    public function suspendUser(Request $request, User $user): JsonResponse
    {
        if ($user->role === 'admin') {
            return response()->json(['message' => 'Cannot suspend an admin.'], 403);
        }

        $request->validate([
            'hours'  => ['required', 'integer', 'in:24,48,96'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $until = now()->addHours($request->hours);

        $user->update([
            'suspended_until'  => $until,
            'suspension_reason' => $request->reason,
        ]);

        // Revoke tokens so active sessions are kicked out immediately
        $user->tokens()->delete();

        return response()->json([
            'message' => "User suspended for {$request->hours}h.",
            'suspended_until' => $until->toIso8601String(),
        ]);
    }

    /**
     * Lift a suspension immediately.
     * DELETE /api/v1/admin/users/{user}/suspend
     */
    public function unsuspendUser(User $user): JsonResponse
    {
        $user->update([
            'suspended_until'  => null,
            'suspension_reason' => null,
        ]);

        return response()->json(['message' => 'Suspension lifted.']);
    }

    /**
     * Mark user as legacy founder.
     * POST /api/v1/admin/users/:id/founder
     */
    public function markFounder(User $user): JsonResponse
    {
        if ($user->is_legacy_founder) {
            return response()->json([
                'message' => 'User is already a legacy founder.',
            ], 409);
        }

        $user->update(['is_legacy_founder' => true]);

        return response()->json([
            'message' => 'User marked as legacy founder.',
            'data' => $user->fresh(),
        ]);
    }

    /**
     * List all transactions with filters.
     * GET /api/v1/admin/transactions
     */
    public function listTransactions(Request $request): JsonResponse
    {
        $query = Transaction::with(['fromWallet.user:id,username', 'toWallet.user:id,username', 'gift:id,name']);

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Sorting
        $allowedSort = ['created_at', 'amount', 'type', 'status'];
        $sortBy = in_array($request->get('sort_by'), $allowedSort) ? $request->get('sort_by') : 'created_at';
        $sortOrder = $request->get('sort_order') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $transactions = $query->paginate($request->get('per_page', 20));

        $mapped = collect($transactions->items())->map(fn (Transaction $tx) => [
            'id' => $tx->id,
            'type' => $tx->type,
            'amount' => $tx->amount,
            'status' => $tx->status,
            'description' => $tx->description,
            'raiffeisen_order_id' => $tx->raiffeisen_order_id,
            'from_user' => $tx->fromWallet?->user?->username,
            'from_user_id' => $tx->fromWallet?->user?->id,
            'to_user' => $tx->toWallet?->user?->username,
            'to_user_id' => $tx->toWallet?->user?->id,
            'gift_name' => $tx->gift?->name,
            'created_at' => $tx->created_at->toIso8601String(),
        ]);

        return response()->json([
            'data' => $mapped,
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    /**
     * Show single transaction details.
     * GET /api/v1/admin/transactions/{transaction}
     */
    public function showTransaction(Transaction $transaction): JsonResponse
    {
        $transaction->load(['fromWallet.user:id,username,email', 'toWallet.user:id,username,email', 'gift', 'media:id,title,type']);

        return response()->json([
            'data' => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'status' => $transaction->status,
                'description' => $transaction->description,
                'raiffeisen_order_id' => $transaction->raiffeisen_order_id,
                'from_user' => $transaction->fromWallet?->user ? [
                    'id' => $transaction->fromWallet->user->id,
                    'username' => $transaction->fromWallet->user->username,
                    'email' => $transaction->fromWallet->user->email,
                ] : null,
                'to_user' => $transaction->toWallet?->user ? [
                    'id' => $transaction->toWallet->user->id,
                    'username' => $transaction->toWallet->user->username,
                    'email' => $transaction->toWallet->user->email,
                ] : null,
                'gift' => $transaction->gift ? [
                    'id' => $transaction->gift->id,
                    'name' => $transaction->gift->name,
                ] : null,
                'media' => $transaction->media ? [
                    'id' => $transaction->media->id,
                    'title' => $transaction->media->title,
                    'type' => $transaction->media->type,
                ] : null,
                'created_at' => $transaction->created_at->toIso8601String(),
                'updated_at' => $transaction->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Reset all economy data (wallets + transactions).
     * POST /api/v1/admin/reset-economy
     */
    public function resetEconomy(Request $request): JsonResponse
    {
        $request->validate([
            'confirm' => ['required', 'string', 'in:RESET'],
        ]);

        $txCount = Transaction::count();
        $walletCount = \App\Models\Wallet::where('balance', '>', 0)->count();

        DB::transaction(function () {
            Transaction::query()->delete();
            \App\Models\Wallet::query()->update(['balance' => 0]);
        });

        return response()->json([
            'message' => "Economy reset: {$txCount} transactions deleted, {$walletCount} wallets zeroed.",
        ]);
    }

    /**
     * List all media for moderation.
     * GET /api/v1/admin/media
     */
    public function listMedia(Request $request): JsonResponse
    {
        $query = Media::with(['user:id,username,email']);

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by creator
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by PPV status
        if ($request->has('is_ppv')) {
            $query->where('is_ppv', filter_var($request->is_ppv, FILTER_VALIDATE_BOOLEAN));
        }

        // Sorting
        $allowedSort = ['created_at', 'type', 'is_ppv'];
        $sortBy = in_array($request->get('sort_by'), $allowedSort) ? $request->get('sort_by') : 'created_at';
        $sortOrder = $request->get('sort_order') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $media = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $media->items(),
            'meta' => [
                'current_page' => $media->currentPage(),
                'last_page' => $media->lastPage(),
                'per_page' => $media->perPage(),
                'total' => $media->total(),
            ],
        ]);
    }

    /**
     * Delete media content.
     * DELETE /api/v1/admin/media/:id
     */
    public function deleteMedia(Media $media): JsonResponse
    {
        $media->delete();

        return response()->json([
            'message' => 'Media deleted successfully.',
        ]);
    }

    /**
     * List all streams for monitoring.
     * GET /api/v1/admin/streams
     */
    public function listStreams(Request $request): JsonResponse
    {
        $query = LiveStream::with(['user:id,username,email', 'currentSession']);

        // Filter by live status
        if ($request->has('is_live')) {
            $query->where('is_live', filter_var($request->is_live, FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by streamer
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Sorting
        $allowedSort = ['created_at', 'is_live'];
        $sortBy = in_array($request->get('sort_by'), $allowedSort) ? $request->get('sort_by') : 'created_at';
        $sortOrder = $request->get('sort_order') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $streams = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $streams->items(),
            'meta' => [
                'current_page' => $streams->currentPage(),
                'last_page' => $streams->lastPage(),
                'per_page' => $streams->perPage(),
                'total' => $streams->total(),
            ],
        ]);
    }

    /**
     * Force end a live stream.
     * POST /api/v1/admin/streams/:id/end
     */
    public function endStream(LiveStream $liveStream): JsonResponse
    {
        if (!$liveStream->is_live) {
            return response()->json([
                'message' => 'Stream is not currently live.',
            ], 409);
        }

        $session = $liveStream->currentSession;
        $liveStream->endStream();

        return response()->json([
            'message' => 'Stream ended successfully.',
            'data' => [
                'stream_id' => $liveStream->id,
                'session_id' => $session?->id,
                'duration_minutes' => $session?->getDurationMinutes(),
            ],
        ]);
    }

    // =============================================
    // COMMUNITY APPROVAL
    // =============================================

    /**
     * List all active communities with search and pagination.
     * GET /api/v1/admin/communities
     */
    public function listCommunities(Request $request): JsonResponse
    {
        $query = Board::where('is_active', true);

        if ($request->has('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(description) LIKE ?', ["%{$search}%"]);
            });
        }

        $communities = $query
            ->with(['members' => fn ($q) => $q->where('role', 'admin')->with('user:id,username')])
            ->withCount('members')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $communities->items(),
            'meta' => [
                'current_page' => $communities->currentPage(),
                'last_page'    => $communities->lastPage(),
                'per_page'     => $communities->perPage(),
                'total'        => $communities->total(),
            ],
        ]);
    }

    /**
     * List communities pending approval.
     * GET /api/v1/admin/communities/pending
     */
    public function listPendingCommunities(Request $request): JsonResponse
    {
        $communities = Board::where('is_active', false)
            ->with(['members' => fn ($q) => $q->where('role', 'admin')->with('user:id,username')])
            ->withCount('members')
            ->orderBy('created_at')
            ->paginate(20);

        return response()->json($communities);
    }

    /**
     * Approve, reject, or deactivate a community.
     * PATCH /api/v1/admin/communities/{board}
     */
    public function reviewCommunity(Request $request, Board $board): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:approve,reject,deactivate',
        ]);

        if ($request->input('action') === 'approve') {
            $board->update(['is_active' => true]);
            return response()->json(['message' => 'Community approved and is now live.', 'data' => $board]);
        }

        if ($request->input('action') === 'deactivate') {
            $board->update(['is_active' => false]);
            return response()->json(['message' => 'Community deactivated.', 'data' => $board]);
        }

        // On reject — delete board, its chat, and all related data (cascade handles the rest)
        if ($board->conversation_id) {
            $board->conversation->users()->detach();
            $board->conversation->delete();
        }
        $board->delete();

        return response()->json(['message' => 'Community rejected and removed.']);
    }

    /**
     * List moments pending admin approval (is_approved IS NULL).
     * GET /api/v1/admin/moments/pending
     */
    public function pendingMoments(Request $request): JsonResponse
    {
        $moments = Media::moments()
            ->whereNull('is_approved')
            ->with(['user:id,username', 'user.profile:user_id,avatar_url'])
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return response()->json([
            'data' => $moments->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'thumbnail_url' => $m->thumbnail_url,
                'user' => [
                    'id' => $m->user->id,
                    'username' => $m->user->username,
                    'avatar_url' => $m->user->profile?->avatar_url,
                ],
                'created_at' => $m->created_at->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $moments->currentPage(),
                'last_page' => $moments->lastPage(),
                'per_page' => $moments->perPage(),
                'total' => $moments->total(),
            ],
        ]);
    }

    /**
     * Approve a moment.
     * POST /api/v1/admin/moments/{media}/approve
     */
    public function approveMoment(Media $media): JsonResponse
    {
        if ($media->type !== 'moment') {
            return response()->json(['message' => 'Not a moment.'], 422);
        }

        $media->update(['is_approved' => true]);

        app(\App\Services\NotificationService::class)->create(
            $media->user,
            'system',
            'Moment odobren',
            "Tvoj moment \"{$media->title}\" je odobren i sada je vidljiv svima.",
            ['media_id' => $media->id],
        );

        return response()->json(['message' => 'Moment approved.']);
    }

    /**
     * Reject (delete) a moment.
     * POST /api/v1/admin/moments/{media}/reject
     */
    public function rejectMoment(Request $request, Media $media): JsonResponse
    {
        if ($media->type !== 'moment') {
            return response()->json(['message' => 'Not a moment.'], 422);
        }

        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $reason = $request->input('reason', 'Sadržaj nije u skladu sa pravilima platforme.');

        app(\App\Services\NotificationService::class)->create(
            $media->user,
            'system',
            'Moment odbijen',
            "Tvoj moment \"{$media->title}\" je odbijen. Razlog: {$reason}",
            ['media_id' => $media->id],
        );

        app(\App\Services\MediaService::class)->deleteMedia($media);

        return response()->json(['message' => 'Moment rejected and deleted.']);
    }
}
