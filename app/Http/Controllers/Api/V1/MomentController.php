<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMomentRequest;
use App\Models\Media;
use App\Services\ContentAccessService;
use App\Services\MediaService;
use App\Services\PlanLimitsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MomentController extends Controller
{
    public function __construct(
        protected MediaService $mediaService,
        protected ContentAccessService $contentAccessService,
        protected PlanLimitsService $planLimitsService
    ) {}

    /**
     * List moments feed with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Media::moments()->with(['user:id,username', 'user.profile:user_id,avatar_url'])->withCount(['comments', 'likes']);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $authUser = $request->user() ?? auth('sanctum')->user();

        $moments = $query->latest()
            ->paginate($request->input('per_page', 20));

        $momentIds = collect($moments->items())->pluck('id')->all();
        $creatorIds = collect($moments->items())->pluck('user_id')->unique()->values()->all();
        $likedIds = [];
        $bookmarkedIds = [];
        $followingIds = [];
        if ($authUser) {
            $likedIds = \App\Models\MediaLike::where('user_id', $authUser->id)
                ->whereIn('media_id', $momentIds)
                ->pluck('media_id')
                ->flip()
                ->all();
            $bookmarkedIds = \App\Models\MediaBookmark::where('user_id', $authUser->id)
                ->whereIn('media_id', $momentIds)
                ->pluck('media_id')
                ->flip()
                ->all();
            $followingIds = \DB::table('follows')
                ->where('follower_id', $authUser->id)
                ->whereIn('following_id', $creatorIds)
                ->pluck('following_id')
                ->flip()
                ->all();
        }

        return response()->json([
            'data' => collect($moments->items())->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type,
                'title' => $m->title,
                'description' => $m->description,
                'url' => $this->mediaService->getMediaUrl($m),
                'thumbnail_url' => $m->thumbnail_url,
                'is_ppv' => $m->is_ppv,
                'price_coins' => $m->price_coins,
                'access_level' => $m->access_level ?? 'public',
                'likes_count' => $m->likes_count ?? 0,
                'comments_count' => $m->comments_count ?? 0,
                'is_liked' => isset($likedIds[$m->id]),
                'is_bookmarked' => isset($bookmarkedIds[$m->id]),
                'is_following' => isset($followingIds[$m->user_id]),
                'user' => $m->user ? ['id' => $m->user->id, 'username' => $m->user->username, 'avatar_url' => $m->user->profile?->avatar_url] : null,
                'created_at' => $m->created_at,
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
     * Get single moment.
     */
    public function show(Request $request, Media $media): JsonResponse
    {
        if ($media->type !== 'moment') {
            return response()->json(['message' => 'Not found'], 404);
        }

        $user = $request->user() ?? auth('sanctum')->user();
        $accessInfo = $this->contentAccessService->getAccessInfo($user, $media);

        if (!$accessInfo['can_access']) {
            return response()->json([
                'message' => 'This content is locked.',
                'is_ppv' => $media->is_ppv,
                'access_info' => $accessInfo,
                'data' => [
                    'id' => $media->id,
                    'title' => $media->title,
                    'thumbnail_url' => $media->thumbnail_url,
                    'user' => $media->user,
                    'created_at' => $media->created_at,
                ],
            ], 403);
        }

        $media->load(['user:id,username', 'user.profile:user_id,avatar_url']);
        $media->loadCount(['likes', 'comments']);

        $isLiked = false;
        $isBookmarked = false;
        $isFollowing = false;
        if ($user) {
            $isLiked = \App\Models\MediaLike::where('user_id', $user->id)->where('media_id', $media->id)->exists();
            $isBookmarked = \App\Models\MediaBookmark::where('user_id', $user->id)->where('media_id', $media->id)->exists();
            $isFollowing = \DB::table('follows')->where('follower_id', $user->id)->where('following_id', $media->user_id)->exists();
        }

        return response()->json([
            'data' => [
                'id' => $media->id,
                'type' => 'moment',
                'title' => $media->title,
                'description' => $media->description,
                'url' => $this->mediaService->getMediaUrl($media),
                'thumbnail_url' => $media->thumbnail_url,
                'is_ppv' => $media->is_ppv,
                'price_coins' => $media->price_coins,
                'access_level' => $media->access_level,
                'likes_count' => $media->likes_count ?? 0,
                'comments_count' => $media->comments_count ?? 0,
                'is_liked' => $isLiked,
                'is_bookmarked' => $isBookmarked,
                'is_following' => $isFollowing,
                'user' => $media->user ? ['id' => $media->user->id, 'username' => $media->user->username, 'avatar_url' => $media->user->profile?->avatar_url] : null,
                'created_at' => $media->created_at,
                'access_info' => $accessInfo,
            ],
        ]);
    }

    /**
     * Upload a new moment.
     */
    public function store(StoreMomentRequest $request): JsonResponse
    {
        // Posting moments requires an active paid subscription (creators are exempt)
        if (!$request->user()->isCreator() && !$request->user()->hasSubscriptionLevel(1)) {
            return response()->json([
                'message' => 'Posting moments requires an active subscription. Upgrade your plan to continue.',
                'error_type' => 'plan_required',
            ], 403);
        }

        // ── Plan limit check ──
        $canPost = $this->planLimitsService->canPostMoment($request->user());
        if ($canPost !== true) {
            return response()->json([
                'message' => $canPost,
                'error_type' => 'plan_limit',
                'max_duration' => $this->planLimitsService->getMaxMomentDuration($request->user()),
            ], 403);
        }

        // Only creators can monetize content with PPV
        $isPpv = $request->user()->isCreator() && $request->boolean('is_ppv');
        $priceCoins = $isPpv ? $request->input('price_coins') : null;

        try {
            $media = $this->mediaService->storeVideo(
                user: $request->user(),
                file: $request->file('video'),
                type: 'moment',
                title: $request->input('title'),
                description: $request->input('description'),
                isPpv: $isPpv,
                priceCoins: $priceCoins,
                accessLevel: $request->input('access_level', 'free'),
                requiredPlanLevel: $request->input('required_plan_level')
            );

            // Store thumbnail if provided
            if ($request->hasFile('thumbnail')) {
                $thumbnailUrl = $this->mediaService->storeThumbnail($request->file('thumbnail'));
                $media->update(['thumbnail_url' => $thumbnailUrl]);
            }

            return response()->json([
                'message' => 'Moment uploaded successfully',
                'data' => [
                    'id' => $media->id,
                    'title' => $media->title,
                    'url' => $this->mediaService->getMediaUrl($media),
                    'thumbnail_url' => $media->thumbnail_url,
                    'is_ppv' => $media->is_ppv,
                    'price_coins' => $media->price_coins,
                ],
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a moment.
     */
    public function destroy(Media $media): JsonResponse
    {
        if ($media->type !== 'moment') {
            return response()->json(['message' => 'Not found'], 404);
        }

        if (Gate::denies('delete', $media)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->mediaService->deleteMedia($media);

        return response()->json([
            'message' => 'Moment deleted successfully',
        ]);
    }
}
