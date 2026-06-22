<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\BoardJoinRequest;
use App\Models\Conversation;
use App\Models\LiveStream;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardController extends Controller
{
    /**
     * List all active boards.
     *
     * GET /api/v1/boards
     */
    public function index(Request $request): JsonResponse
    {
        $query = Board::active()->withCount(['posts', 'members']);

        if ($request->has('country')) {
            $query->where('country_code', $request->input('country'));
        }

        if ($request->has('q')) {
            $search = $request->input('q');
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(city) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }

        $boards = $query->orderBy('name')->get();

        return response()->json([
            'data' => $boards,
        ]);
    }

    /**
     * Get a single board by slug.
     *
     * GET /api/v1/boards/{board}
     */
    public function show(Request $request, Board $board): JsonResponse
    {
        $board->loadCount('posts');

        $data = $board->toArray();

        // For authenticated users, include join request status
        if ($user = $request->user()) {
            $joinRequest = BoardJoinRequest::where('board_id', $board->id)
                ->where('user_id', $user->id)
                ->first();
            $data['has_pending_join_request'] = $joinRequest?->status === 'pending';
        } else {
            $data['has_pending_join_request'] = false;
        }

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Create a new board (community).
     *
     * POST /api/v1/boards
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Platform admins and creators can always create communities.
        // Other users need a plan that grants can_create_community.
        if (!$user->isCreator()) {
            $planLimitsService = app(\App\Services\PlanLimitsService::class);
            if (!$planLimitsService->canCreateCommunity($user)) {
                return response()->json([
                    'message' => 'Your current plan does not allow creating new communities. Please upgrade.',
                ], 403);
            }
        }

        $request->validate([
            'name' => 'required|string|max:100',
            'country_code' => 'required|string|size:2',
            'city' => 'nullable|string|max:100',
            'description' => 'required|string|max:1000',
            'is_public' => 'required|boolean',
            'banner' => 'nullable|image|max:5120', // Up to 5MB
        ]);

        $slug = \Illuminate\Support\Str::slug($request->input('name'));
        if (Board::where('slug', $slug)->exists()) {
            $slug .= '-' . \Illuminate\Support\Str::random(4);
        }

        $imageUrl = null;
        if ($request->hasFile('banner')) {
            $path = $request->file('banner')->store('board-banners', 'public');
            $imageUrl = asset('storage/' . $path);
        }

        $board = Board::create([
            'name' => $request->input('name'),
            'slug' => $slug,
            'country_code' => strtoupper($request->input('country_code')),
            'city' => $request->input('city'),
            'description' => $request->input('description'),
            'is_public' => $request->input('is_public'),
            'image_url' => $imageUrl,
            'is_active' => false, // Inactive until approved by platform admin
        ]);

        // Add creator as board admin
        $board->members()->create([
            'user_id' => $request->user()->id,
            'role' => 'admin',
        ]);

        // Auto-create the community group chat
        $conversation = Conversation::create([
            'name' => $board->name,
            'is_group' => true,
        ]);
        $conversation->users()->attach($request->user()->id, ['joined_at' => now()]);
        $board->update(['conversation_id' => $conversation->id]);

        return response()->json([
            'message' => 'Community created successfully. It will be visible once approved by the platform admin.',
            'data' => $board,
        ], 201);
    }
    /**
     * Get upcoming and live streams for a board.
     *
     * GET /api/v1/boards/{board}/streams
     */
    public function streams(Board $board): JsonResponse
    {
        $streams = LiveStream::where('board_id', $board->id)
            ->where(function ($q) {
                $q->where('is_live', true)
                  ->orWhere(function ($q2) {
                      $q2->whereNotNull('scheduled_at')
                         ->where('scheduled_at', '>', now()->subHours(1))
                         ->where('is_live', false);
                  });
            })
            ->with('user:id,username')
            ->orderByRaw('is_live DESC, scheduled_at ASC NULLS LAST')
            ->get();

        return response()->json([
            'data' => $streams->map(fn ($s) => [
                'id'           => $s->id,
                'title'        => $s->title,
                'stream_mode'  => $s->stream_mode,
                'is_live'      => $s->is_live,
                'scheduled_at' => $s->scheduled_at?->toIso8601String(),
                'streamer'     => ['id' => $s->user->id, 'username' => $s->user->username],
            ]),
        ]);
    }

    /**
     * Schedule a stream for a board.
     *
     * POST /api/v1/boards/{board}/streams
     */
    public function scheduleStream(Request $request, Board $board): JsonResponse
    {
        $user = $request->user();

        if (!$user->isCreator() || (!$user->isAdmin() && !$board->userIsAdmin($user->id))) {
            return response()->json(['message' => 'Only creators who are board admins can schedule streams.'], 403);
        }

        $validated = $request->validate([
            'title'        => 'required|string|max:100',
            'scheduled_at' => 'required|date|after:now',
            'stream_mode'  => 'sometimes|in:scena,moments',
        ]);

        $stream = LiveStream::create([
            'user_id'      => $user->id,
            'board_id'     => $board->id,
            'title'        => $validated['title'],
            'stream_mode'  => $validated['stream_mode'] ?? 'scena',
            'scheduled_at' => $validated['scheduled_at'],
            'is_live'      => false,
        ]);

        // Notify all board members
        $notifier = app(NotificationService::class);
        $scheduledDate = $stream->scheduled_at->format('d.m.Y H:i');
        $board->members()->with('user')->where('user_id', '!=', $user->id)->get()
            ->each(function ($member) use ($notifier, $board, $stream, $scheduledDate) {
                $notifier->create(
                    $member->user,
                    'system',
                    'Zakazan stream',
                    "Zakazan je stream u zajednici {$board->name}: \"{$stream->title}\" — {$scheduledDate}",
                    ['board_slug' => $board->slug, 'stream_id' => $stream->id]
                );
            });

        return response()->json([
            'data' => [
                'id'           => $stream->id,
                'title'        => $stream->title,
                'stream_mode'  => $stream->stream_mode,
                'is_live'      => $stream->is_live,
                'scheduled_at' => $stream->scheduled_at->toIso8601String(),
                'streamer'     => ['id' => $user->id, 'username' => $user->username],
            ],
        ], 201);
    }

    /**
     * Join a board.
     *
     * POST /api/v1/boards/{board}/join
     */
    public function join(Request $request, Board $board): JsonResponse
    {
        $user = $request->user();

        if ($board->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You are already a member.'], 422);
        }

        if (!$board->is_public) {
            // Private board — create a join request (idempotent)
            $existing = BoardJoinRequest::where('board_id', $board->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Join request already sent.',
                    'status' => $existing->status,
                ], 422);
            }

            BoardJoinRequest::create([
                'board_id' => $board->id,
                'user_id' => $user->id,
                'status' => 'pending',
            ]);

            return response()->json(['message' => 'Join request sent. Waiting for admin approval.'], 202);
        }

        $board->members()->create([
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        // Add to community group chat
        if ($board->conversation_id) {
            $board->conversation->users()->syncWithoutDetaching([
                $user->id => ['joined_at' => now()],
            ]);
        }

        return response()->json(['message' => 'Joined successfully.']);
    }

    /**
     * Leave a board.
     *
     * POST /api/v1/boards/{board}/leave
     */
    public function leave(Request $request, Board $board): JsonResponse
    {
        $user = $request->user();

        $member = $board->members()->where('user_id', $user->id)->first();
        if (!$member) {
            return response()->json(['message' => 'You are not a member of this community.'], 422);
        }

        // Prevent the last admin from leaving
        if ($member->role === 'admin' && $board->members()->where('role', 'admin')->count() === 1) {
            return response()->json(['message' => 'You are the only admin. Transfer admin role before leaving.'], 422);
        }

        $board->members()->where('user_id', $user->id)->delete();

        // Remove from community group chat
        if ($board->conversation_id) {
            $board->conversation->users()->detach($user->id);
        }

        return response()->json(['message' => 'Left successfully.']);
    }
}