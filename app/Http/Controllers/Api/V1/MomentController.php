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
        $query = Media::moments()->with('user:id,username');

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $moments = $query->latest()
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => $moments->items(),
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

        $media->load('user:id,username');

        return response()->json([
            'data' => [
                'id' => $media->id,
                'title' => $media->title,
                'description' => $media->description,
                'url' => $this->mediaService->getMediaUrl($media),
                'thumbnail_url' => $media->thumbnail_url,
                'is_ppv' => $media->is_ppv,
                'price_coins' => $media->price_coins,
                'access_level' => $media->access_level,
                'user' => $media->user,
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
        // Posting moments requires an active paid subscription
        if (!$request->user()->hasSubscriptionLevel(1)) {
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
