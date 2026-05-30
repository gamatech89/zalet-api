<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBoardPostRequest;
use App\Models\Board;
use App\Models\BoardPost;
use App\Services\PlanLimitsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardPostController extends Controller
{
    /**
     * Get plan limits and usage for board posting.
     *
     * GET /api/v1/boards/plan-info
     */
    public function getCurrentPlanInfo(Request $request): JsonResponse
    {
        $planLimitsService = app(PlanLimitsService::class);
        $user = $request->user();

        return response()->json([
            'data' => $planLimitsService->getPlanInfo($user)
        ]);
    }

    /**
     * List posts in a board (with filtering).
     *
     * GET /api/v1/boards/{board}/posts
     */
    public function index(Request $request, Board $board): JsonResponse
    {
        $query = $board->posts()
            ->active()
            ->approved()
            ->with(['user:id,username,role', 'user.profile:id,user_id,avatar_url', 'place'])
            ->withCount('comments');

        // Category filter
        if ($request->has('category') && $request->input('category') !== 'all') {
            $query->byCategory($request->input('category'));
        }

        // Type filter
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Search
        if ($request->has('q')) {
            $search = $request->input('q');
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(title) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(body) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }

        // Pinned posts first, then newest
        $posts = $query
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        // Add is_liked for authenticated user
        $userId = auth('sanctum')->id();
        if ($userId) {
            $postIds = $posts->pluck('id')->toArray();
            $likedPostIds = \DB::table('board_post_likes')
                ->where('user_id', $userId)
                ->whereIn('post_id', $postIds)
                ->pluck('post_id')
                ->toArray();

            $posts->getCollection()->transform(function ($post) use ($likedPostIds) {
                $post->is_liked = in_array($post->id, $likedPostIds);
                return $post;
            });
        }

        return response()->json($posts);
    }

    /**
     * Create a new board post.
     *
     * POST /api/v1/boards/{board}/posts
     */
    public function store(StoreBoardPostRequest $request, Board $board): JsonResponse
    {
        // ── Plan limit check ──
        $planLimitsService = app(PlanLimitsService::class);
        $postCheck = $planLimitsService->canPostToCommunity($request->user());

        if (!$postCheck['allowed'] && $postCheck['coin_cost'] === 0) {
            return response()->json([
                'message' => $postCheck['reason'],
                'error_type' => 'plan_limit',
                'coin_cost' => 0,
            ], 403);
        }

        // Check if user has enough coins if they are over the limit
        if (!$postCheck['allowed'] && $postCheck['coin_cost'] > 0) {
            $balance = $request->user()->wallet?->balance ?? 0;
            if ($balance < $postCheck['coin_cost']) {
                return response()->json([
                    'message' => "Need {$postCheck['coin_cost']} ZaletCoins to post. Your balance: {$balance} ZC.",
                    'error_type' => 'plan_limit',
                    'coin_cost' => $postCheck['coin_cost'],
                ], 403);
            }

            // Deduct coins
            $request->user()->wallet->decrement('balance', $postCheck['coin_cost']);
            $coinCharged = $postCheck['coin_cost'];
        } else {
            $coinCharged = 0;
        }

        $userId = $request->user()->id;

        // On private boards, posts from non-admins/mods go into pending review
        $status = 'approved';
        if (!$board->is_public && !$board->userCanManage($userId)) {
            $status = 'pending';
        }

        $post = $board->posts()->create([
            'user_id' => $userId,
            'title' => $request->input('title'),
            'body' => $request->input('body'),
            'category' => $request->input('category'),
            'type' => $request->input('type', 'offer'),
            'images' => $request->input('images', []),
            'location_label' => $request->input('location_label'),
            'place_id' => $request->input('place_id'),
            'status' => $status,
        ]);

        $post->load(['user:id,username,role', 'user.profile:id,user_id,avatar_url', 'place']);

        $message = $status === 'pending'
            ? 'Post submitted for review. It will appear after admin approval.'
            : 'Post created successfully.';

        return response()->json([
            'message' => $message,
            'data' => $post,
        ], 201);
    }

    /**
     * Get a single board post with full details.
     *
     * GET /api/v1/boards/{board}/posts/{post}
     */
    public function show(Board $board, BoardPost $post): JsonResponse
    {
        abort_if($post->board_id !== $board->id, 404);

        $post->load([
            'user:id,username,role',
            'user.profile:id,user_id,avatar_url,hometown_city,hometown_country,current_city,current_country',
            'comments' => fn($q) => $q->orderBy('created_at')->limit(50),
            'comments.user:id,username,role',
            'comments.user.profile:id,user_id,avatar_url',
        ]);
        $post->loadCount('comments');

        // Increment views
        $post->increment('views_count');

        // Check if liked by auth user
        $userId = auth('sanctum')->id();
        $post->is_liked = $userId ? $post->isLikedBy($userId) : false;

        return response()->json([
            'data' => $post,
        ]);
    }

    /**
     * Delete a board post (owner only).
     *
     * DELETE /api/v1/boards/{board}/posts/{post}
     */
    public function destroy(Request $request, Board $board, BoardPost $post): JsonResponse
    {
        abort_if($post->board_id !== $board->id, 404);
        abort_if($post->user_id !== $request->user()->id && !$request->user()->isAdmin(), 403);

        $post->delete();

        return response()->json([
            'message' => 'Post deleted successfully.',
        ]);
    }

    /**
     * Like/unlike a board post (toggle).
     *
     * POST /api/v1/boards/{board}/posts/{post}/like
     */
    public function toggleLike(Request $request, Board $board, BoardPost $post): JsonResponse
    {
        abort_if($post->board_id !== $board->id, 404);

        $userId = $request->user()->id;
        $exists = $post->likes()->where('user_id', $userId)->exists();

        if ($exists) {
            $post->likes()->detach($userId);
            $post->decrement('likes_count');
            $liked = false;
        }
        else {
            $post->likes()->attach($userId);
            $post->increment('likes_count');
            $liked = true;
        }

        return response()->json([
            'liked' => $liked,
            'likes_count' => $post->fresh()->likes_count,
        ]);
    }

    /**
     * Add a comment to a board post.
     *
     * POST /api/v1/boards/{board}/posts/{post}/comments
     */
    public function addComment(Request $request, Board $board, BoardPost $post): JsonResponse
    {
        abort_if($post->board_id !== $board->id, 404);

        $request->validate([
            'body' => 'required|string|max:1000',
        ]);

        $comment = $post->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->input('body'),
        ]);

        $comment->load(['user:id,username,role', 'user.profile:id,user_id,avatar_url']);

        return response()->json([
            'message' => 'Comment added.',
            'data' => $comment,
        ], 201);
    }

    /**
     * Upload an image for a board post.
     *
     * POST /api/v1/boards/{board}/upload-image
     */
    public function uploadImage(Request $request, Board $board): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|max:5120', // 5MB max
        ]);

        $path = $request->file('image')->store('board-images', 'public');
        $url = asset('storage/' . $path);

        return response()->json([
            'url' => $url,
        ]);
    }
}