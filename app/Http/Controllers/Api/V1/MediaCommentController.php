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
        $comments = MediaComment::where('media_id', $media->id)
            ->whereNull('parent_id')
            ->with(['user:id,username', 'replies.user:id,username'])
            ->latest()
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => $comments->items(),
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
