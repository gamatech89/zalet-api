<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\MediaBookmark;
use App\Models\MediaLike;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaInteractionController extends Controller
{
    /**
     * Toggle like on a media item.
     * POST /api/v1/media/{media}/like
     */
    public function toggleLike(Request $request, Media $media): JsonResponse
    {
        $user = $request->user();
        $existing = MediaLike::where('user_id', $user->id)
            ->where('media_id', $media->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $media->decrement('likes_count');
            return response()->json([
                'liked' => false,
                'likes_count' => max(0, $media->fresh()->likes_count),
            ]);
        }

        MediaLike::create([
            'user_id' => $user->id,
            'media_id' => $media->id,
        ]);
        $media->increment('likes_count');

        // Notify media owner (not self-like)
        if ($media->user_id !== $user->id) {
            $owner = $media->user()->first();
            if ($owner) {
                app(NotificationService::class)->create(
                    $owner,
                    'like',
                    'Neko je lajkovao vaš sadržaj',
                    "@{$user->username} je lajkovao/la vaš sadržaj.",
                    ['media_id' => $media->id, 'liker_id' => $user->id, 'liker_username' => $user->username],
                );
            }
        }

        return response()->json([
            'liked' => true,
            'likes_count' => $media->fresh()->likes_count,
        ]);
    }

    /**
     * Toggle bookmark on a media item.
     * POST /api/v1/media/{media}/bookmark
     */
    public function toggleBookmark(Request $request, Media $media): JsonResponse
    {
        $user = $request->user();
        $existing = MediaBookmark::where('user_id', $user->id)
            ->where('media_id', $media->id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['bookmarked' => false]);
        }

        MediaBookmark::create([
            'user_id' => $user->id,
            'media_id' => $media->id,
        ]);

        return response()->json(['bookmarked' => true]);
    }

    /**
     * Track a view on a media item (fire-and-forget, once per session ideally).
     * POST /api/v1/media/{media}/view
     */
    public function trackView(Media $media): JsonResponse
    {
        $media->increment('views_count');

        return response()->json([
            'views_count' => $media->fresh()->views_count,
        ]);
    }

    /**
     * List bookmarked media for the authenticated user.
     * GET /api/v1/profile/bookmarks
     */
    public function listBookmarks(Request $request): JsonResponse
    {
        $user = $request->user();
        $bookmarks = MediaBookmark::where('user_id', $user->id)
            ->with(['media' => function ($q) {
                $q->with('user:id,username')->select(
                    'id', 'user_id', 'type', 'title', 'description',
                    'url', 'thumbnail_url',
                    'is_ppv', 'price_coins', 'access_level',
                    'views_count', 'likes_count', 'comments_count',
                    'created_at'
                );
            }])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $bookmarks->map(fn($b) => $b->media)->filter()->values(),
            'meta' => [
                'current_page' => $bookmarks->currentPage(),
                'last_page'    => $bookmarks->lastPage(),
                'total'        => $bookmarks->total(),
            ],
        ]);
    }
}
