<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\MediaComment;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaCommentController extends Controller
{
    /**
     * List comments for a media item (top-level + nested replies).
     * GET /api/v1/media/{media}/comments
     */
    public function index(Request $request, Media $media): JsonResponse
    {
        $authUser = $request->user() ?? auth('sanctum')->user();

        $comments = MediaComment::where('media_id', $media->id)
            ->whereNull('parent_id')
            ->with(['user:id,username', 'replies' => fn ($q) => $q->with('user:id,username')->withCount('likes')->latest()])
            ->withCount('likes')
            ->latest()
            ->paginate($request->input('per_page', 15));

        $commentIds = collect($comments->items())->pluck('id')->all();
        $likedIds = [];
        if ($authUser && !empty($commentIds)) {
            $allIds = collect($comments->items())
                ->flatMap(fn ($c) => array_merge([$c->id], $c->replies->pluck('id')->all()))
                ->all();
            $likedIds = \App\Models\MediaCommentLike::where('user_id', $authUser->id)
                ->whereIn('comment_id', $allIds)
                ->pluck('comment_id')
                ->flip()
                ->all();
        }

        $items = collect($comments->items())->map(function ($c) use ($likedIds) {
            $arr = $c->toArray();
            $arr['likes_count'] = $c->likes_count ?? 0;
            $arr['is_liked'] = isset($likedIds[$c->id]);
            $arr['replies'] = collect($c->replies)->map(function ($r) use ($likedIds) {
                $ra = $r->toArray();
                $ra['likes_count'] = $r->likes_count ?? 0;
                $ra['is_liked'] = isset($likedIds[$r->id]);
                return $ra;
            })->values()->all();
            return $arr;
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'per_page' => $comments->perPage(),
                'total' => $comments->total(),
            ],
        ]);
    }

    /**
     * Create a comment on a media item.
     * POST /api/v1/media/{media}/comments
     */
    public function store(Request $request, Media $media): JsonResponse
    {
        $request->validate([
            'body' => 'required|string|max:2000',
            'parent_id' => 'nullable|uuid|exists:media_comments,id',
        ]);

        // If replying, ensure parent belongs to same media
        if ($request->filled('parent_id')) {
            $parent = MediaComment::where('id', $request->input('parent_id'))
                ->where('media_id', $media->id)
                ->firstOrFail();
        }

        $comment = MediaComment::create([
            'user_id' => $request->user()->id,
            'media_id' => $media->id,
            'parent_id' => $request->input('parent_id'),
            'body' => $request->input('body'),
        ]);

        $media->increment('comments_count');
        $comment->load('user:id,username');

        $commenter = $request->user();

        // Notify media owner (not self-comment)
        if ($media->user_id !== $commenter->id) {
            $owner = $media->user()->first();
            if ($owner) {
                app(NotificationService::class)->create(
                    $owner,
                    'comment',
                    'Novi komentar',
                    "@{$commenter->username} je komentarisao/la vaš sadržaj.",
                    ['media_id' => $media->id, 'comment_id' => $comment->id, 'commenter_id' => $commenter->id],
                );
            }
        }

        return response()->json([
            'data' => $comment,
        ], 201);
    }

    /**
     * Toggle like on a comment.
     * POST /api/v1/media/{media}/comments/{comment}/like
     */
    public function toggleLike(Request $request, Media $media, MediaComment $comment): JsonResponse
    {
        if ($comment->media_id !== $media->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $userId = $request->user()->id;
        $existing = \App\Models\MediaCommentLike::where('user_id', $userId)
            ->where('comment_id', $comment->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            \App\Models\MediaCommentLike::create([
                'user_id' => $userId,
                'comment_id' => $comment->id,
            ]);
            $liked = true;
        }

        $likesCount = \App\Models\MediaCommentLike::where('comment_id', $comment->id)->count();

        return response()->json([
            'liked' => $liked,
            'likes_count' => $likesCount,
        ]);
    }

    /**
     * Delete own comment.
     * DELETE /api/v1/media/{media}/comments/{comment}
     */
    public function destroy(Request $request, Media $media, MediaComment $comment): JsonResponse
    {
        if ($comment->media_id !== $media->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if ($comment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Count the comment + its replies for accurate decrement
        $repliesCount = $comment->replies()->count();
        $comment->delete();
        $media->decrement('comments_count', 1 + $repliesCount);

        return response()->json(['message' => 'Comment deleted']);
    }
}
