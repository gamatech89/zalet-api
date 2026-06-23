<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventType;
use App\Http\Controllers\Controller;
use App\Models\UserEvent;
use App\Enums\MediaProvider;
use App\Enums\MediaType;
use App\Services\Achievements\Payloads\MediaPostedPayload;
use App\Models\AppSetting;
use App\Models\Media;
use App\Services\ContentAccessService;
use App\Services\EmbedService;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ScenaController extends Controller
{
    public function __construct(
        protected MediaService $mediaService,
        protected ContentAccessService $contentAccessService,
        protected EmbedService $embedService
    ) {}

    /**
     * List scena (long-form) feed with pagination.
     *
     * GET /api/v1/scena
     */
    public function index(Request $request): JsonResponse
    {
        $query = Media::longForm()->with(['user:id,username', 'tags:id,name,label,color']);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // Full-text search on title and description
        if ($request->filled('q')) {
            $term = '%' . strtolower($request->input('q')) . '%';
            $isPgsql = config('database.default') === 'pgsql'
                || config('database.connections.' . config('database.default') . '.driver') === 'pgsql';

            $query->where(function ($q) use ($term, $isPgsql) {
                if ($isPgsql) {
                    $q->whereRaw('unaccent(LOWER(title)) LIKE unaccent(?)', [$term])
                      ->orWhereRaw('unaccent(LOWER(description)) LIKE unaccent(?)', [$term]);
                } else {
                    $q->whereRaw('LOWER(title) LIKE ?', [$term])
                      ->orWhereRaw('LOWER(description) LIKE ?', [$term]);
                }
            });
        }

        // Tag-based filtering (proper)
        if ($request->filled('tag')) {
            $query->withTag($request->input('tag'));
        }

        $scena = $query->latest()
            ->paginate($request->input('per_page', 12));

        return response()->json([
            'data' => $scena->items(),
            'meta' => [
                'current_page' => $scena->currentPage(),
                'last_page' => $scena->lastPage(),
                'per_page' => $scena->perPage(),
                'total' => $scena->total(),
            ],
        ]);
    }

    /**
     * Get single scena video.
     *
     * GET /api/v1/scena/{media}
     */
    public function show(Request $request, Media $media): JsonResponse
    {
        if ($media->type !== 'long_form') {
            return response()->json(['message' => 'Not found'], 404);
        }

        // Use sanctum guard to optionally resolve user on public route
        $user = $request->user() ?? auth('sanctum')->user();
        $accessInfo = $this->contentAccessService->getAccessInfo($user, $media);

        if (!$accessInfo['can_access']) {
            return response()->json([
                'message' => 'This content is locked.',
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

        $media->load(['user:id,username', 'tags:id,name,label,color']);

        // Build embed_url for non-native providers
        $embedUrl = ($media->provider && $media->provider !== 'native')
            ? $this->embedService->getEmbedUrl($media->url)
            : null;

        return response()->json([
            'data' => [
                'id' => $media->id,
                'title' => $media->title,
                'description' => $media->description,
                'url' => $this->mediaService->getMediaUrl($media),
                'embed_url' => $embedUrl,
                'thumbnail_url' => $media->thumbnail_url,
                'provider' => $media->provider,
                'is_ppv' => $media->is_ppv,
                'price_coins' => $media->price_coins,
                'access_level' => $media->access_level,
                'user' => $media->user,
                'tags' => $media->tags,
                'created_at' => $media->created_at,
                'access_info' => $accessInfo,
                // Interaction counters
                'views_count' => $media->views_count ?? 0,
                'likes_count' => $media->likes_count ?? 0,
                'comments_count' => $media->comments_count ?? 0,
                // User-specific flags
                'is_liked' => $media->likedBy($user),
                'is_bookmarked' => $media->bookmarkedBy($user),
            ],
        ]);
    }

    /**
     * Upload a new scena (long-form) video.
     *
     * POST /api/v1/scena
     */
    public function store(Request $request): JsonResponse
    {
        // Only creators can upload Scena content
        if (!$request->user()->isCreator()) {
            return response()->json([
                'message' => 'Only creators can upload Scena videos.',
                'error_type' => 'role_restricted',
            ], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'video' => 'required|file|mimes:mp4,mov,avi|max:512000', // 500MB max
            'thumbnail' => 'nullable|image|mimes:jpeg,png,webp|max:5120', // 5MB
            'is_ppv' => 'sometimes|boolean',
            'price_coins' => 'required_if:is_ppv,true|numeric|min:1',
            'access_level' => 'sometimes|string|in:free,premium,vip',
            'required_plan_level' => 'nullable|integer|min:1|max:10',
        ]);

        if ($request->boolean('is_ppv')) {
            $this->checkPpvLimits($request->user()->id, isNewUpload: true);
        }

        try {
            $media = $this->mediaService->storeVideo(
                user: $request->user(),
                file: $request->file('video'),
                type: 'long_form',
                title: $request->input('title'),
                description: $request->input('description'),
                isPpv: $request->boolean('is_ppv'),
                priceCoins: $request->input('price_coins'),
                accessLevel: $request->input('access_level', 'free'),
                requiredPlanLevel: $request->input('required_plan_level')
            );

            // Store thumbnail if provided
            if ($request->hasFile('thumbnail')) {
                $thumbnailUrl = $this->mediaService->storeThumbnail($request->file('thumbnail'));
                $media->update(['thumbnail_url' => $thumbnailUrl]);
            }

            UserEvent::record($request->user(), EventType::MEDIA_POSTED, new MediaPostedPayload(
                mediaType: MediaType::LONG_FORM,
                provider: MediaProvider::NATIVE,
            ));

            return response()->json([
                'message' => 'Scena video uploaded successfully',
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
     * Update scena metadata (title, description, thumbnail, access settings).
     * Video file is immutable — only metadata can change.
     *
     * PATCH /api/v1/scena/{media}
     */
    public function update(Request $request, Media $media): JsonResponse
    {
        if ($media->type !== 'long_form') {
            return response()->json(['message' => 'Not found'], 404);
        }

        if (Gate::denies('update', $media)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'title'               => 'sometimes|string|max:255',
            'description'         => 'nullable|string|max:2000',
            'thumbnail'           => 'nullable|image|mimes:jpeg,png,webp|max:5120',
            'is_ppv'              => 'sometimes|boolean',
            'price_coins'         => 'required_if:is_ppv,true|numeric|min:1',
            'access_level'        => 'sometimes|string|in:free,premium,vip',
            'required_plan_level' => 'nullable|integer|min:1|max:10',
        ]);

        if ($request->boolean('is_ppv') && !$media->is_ppv) {
            $this->checkPpvLimits($request->user()->id, isNewUpload: false);
        }

        $data = $request->only(['title', 'description', 'is_ppv', 'access_level', 'required_plan_level']);

        if ($request->boolean('is_ppv')) {
            $data['price_coins'] = $request->input('price_coins');
        } else {
            $data['price_coins'] = null;
        }

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail_url'] = $this->mediaService->storeThumbnail($request->file('thumbnail'));
        }

        $media->update($data);

        return response()->json([
            'message' => 'Scena updated successfully',
            'data'    => [
                'id'            => $media->id,
                'title'         => $media->title,
                'description'   => $media->description,
                'thumbnail_url' => $media->thumbnail_url,
                'is_ppv'        => $media->is_ppv,
                'price_coins'   => $media->price_coins,
                'access_level'  => $media->access_level,
            ],
        ]);
    }

    /**
     * Delete a scena video.
     *
     * DELETE /api/v1/scena/{media}
     */
    public function destroy(Media $media): JsonResponse
    {
        if ($media->type !== 'long_form') {
            return response()->json(['message' => 'Not found'], 404);
        }

        if (Gate::denies('delete', $media)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->mediaService->deleteMedia($media);

        return response()->json([
            'message' => 'Scena video deleted successfully',
        ]);
    }

    /**
     * Create a scena from an embed URL (YouTube/Vimeo/Dailymotion).
     *
     * POST /api/v1/scena/embed
     */
    public function embed(Request $request): JsonResponse
    {
        $request->validate([
            'url' => ['required', 'url', 'max:500', function ($attribute, $value, $fail) {
                if (!$this->embedService->isValidUrl($value)) {
                    $fail('The URL must be from YouTube, Vimeo, or Dailymotion.');
                }
            }],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'is_ppv' => 'sometimes|boolean',
            'price_coins' => 'required_if:is_ppv,true|numeric|min:1',
            'access_level' => 'sometimes|string|in:free,premium,vip',
            'required_plan_level' => 'nullable|integer|min:1|max:10',
        ]);

        if ($request->boolean('is_ppv')) {
            $this->checkPpvLimits($request->user()->id, isNewUpload: true);
        }

        $url = $request->input('url');
        $metadata = $this->embedService->extractMetadata($url);

        $media = Media::create([
            'user_id' => $request->user()->id,
            'type' => 'long_form',
            'provider' => $metadata['provider'],
            'url' => $url,
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'thumbnail_url' => $metadata['thumbnail_url'],
            'size_bytes' => 0,
            'is_ppv' => $request->boolean('is_ppv'),
            'price_coins' => $request->boolean('is_ppv') ? $request->input('price_coins') : null,
            'access_level' => $request->input('access_level', 'free'),
            'required_plan_level' => $request->input('required_plan_level'),
        ]);

        UserEvent::record($request->user(), EventType::MEDIA_POSTED, new MediaPostedPayload(
            mediaType: MediaType::LONG_FORM,
            provider: MediaProvider::from($metadata['provider']),
        ));

        return response()->json([
            'message' => 'Scena embed created successfully',
            'data' => [
                'id' => $media->id,
                'title' => $media->title,
                'url' => $url,
                'thumbnail_url' => $media->thumbnail_url,
                'is_ppv' => $media->is_ppv,
                'price_coins' => $media->price_coins,
            ],
        ], 201);
    }

    private function checkPpvLimits(int $userId, bool $isNewUpload): void
    {
        if ($isNewUpload) {
            $monthlyLimit = AppSetting::get('ppv_monthly_limit', 3);
            $thisMonthCount = Media::where('user_id', $userId)
                ->where('is_ppv', true)
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count();
            if ($thisMonthCount >= $monthlyLimit) {
                abort(422, "Dostigli ste mesečni limit od {$monthlyLimit} PPV videa.");
            }
        }

        $maxPercent = AppSetting::get('ppv_content_percent', 25);
        $totalCount = Media::where('user_id', $userId)->count();
        $ppvCount = Media::where('user_id', $userId)->where('is_ppv', true)->count();
        $effectiveTotal = $isNewUpload ? $totalCount + 1 : $totalCount;
        $maxAllowed = max(1, (int) floor($effectiveTotal * ($maxPercent / 100)));

        if ($ppvCount + 1 > $maxAllowed) {
            abort(422, "Ne možete imati više od {$maxPercent}% PPV sadržaja (limit: {$maxAllowed} od {$effectiveTotal} videa).");
        }
    }
}