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

class AdminController extends Controller
{
    /**
     * Get platform statistics.
     * GET /api/v1/admin/stats
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'users' => [
                'total' => User::count(),
                'admins' => User::where('role', 'admin')->count(),
                'creators' => User::where('role', 'creator')->count(),
                'regular' => User::where('role', 'user')->count(),
                'legacy_founders' => User::where('is_legacy_founder', true)->count(),
            ],
            'transactions' => [
                'total' => Transaction::count(),
                'total_volume' => (float) Transaction::where('status', 'completed')->sum('amount'),
                'deposits' => (float) Transaction::where('type', 'deposit')->where('status', 'completed')->sum('amount'),
                'withdrawals' => (float) Transaction::where('type', 'withdrawal')->where('status', 'completed')->sum('amount'),
            ],
            'content' => [
                'media_total' => Media::count(),
                'moments' => Media::where('type', 'moment')->count(),
                'cinema' => Media::where('type', 'embed')->count(),
                'long_form' => Media::where('type', 'long_form')->count(),
            ],
            'streams' => [
                'total' => LiveStream::count(),
                'currently_live' => LiveStream::where('is_live', true)->count(),
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
        $query = User::with(['profile', 'wallet']);

        // Filter by role
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Filter by legacy founder status
        if ($request->has('is_legacy_founder')) {
            $query->where('is_legacy_founder', filter_var($request->is_legacy_founder, FILTER_VALIDATE_BOOLEAN));
        }

        // Search by email or username (case-insensitive)
        if ($request->has('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(email) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(username) LIKE ?', ["%{$search}%"]);
            });
        }

        // Sorting
        $allowedSort = ['created_at', 'username', 'email', 'role', 'last_ip'];
        $sortBy = in_array($request->get('sort_by'), $allowedSort) ? $request->get('sort_by') : 'created_at';
        $sortOrder = $request->get('sort_order') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $users = $query->select([
            'id', 'username', 'email', 'role', 'is_active', 'is_legacy_founder',
            'last_ip', 'registration_ip', 'suspended_until', 'suspension_reason', 'created_at',
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
        $query = Transaction::with(['fromWallet.user', 'toWallet.user']);

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

        return response()->json([
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
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
}
