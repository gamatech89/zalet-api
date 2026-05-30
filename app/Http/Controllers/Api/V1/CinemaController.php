<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCinemaRequest;
use App\Models\Media;
use App\Services\ContentAccessService;
use App\Services\EmbedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CinemaController extends Controller
{
    public function __construct(
        protected EmbedService $embedService,
        protected ContentAccessService $contentAccessService
    ) {}

    /**
     * List cinema (embed) feed with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Media::embeds()->with('user:id,username');

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $cinema = $query->latest()
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => $cinema->items(),
            'meta' => [
                'current_page' => $cinema->currentPage(),
                'last_page' => $cinema->lastPage(),
                'per_page' => $cinema->perPage(),
                'total' => $cinema->total(),
            ],
        ]);
    }

    /**
     * Get single cinema item.
     */
    public function show(Request $request, Media $media): JsonResponse
    {
        if ($media->type !== 'embed') {
            return response()->json(['message' => 'Not found'], 404);
        }

        $user = $request->user();
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
                'url' => $media->url,
                'embed_url' => $this->embedService->getEmbedUrl($media->url),
                'thumbnail_url' => $media->thumbnail_url,
                'provider' => $media->provider,
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
     * Create a new cinema embed.
     */
    public function store(StoreCinemaRequest $request): JsonResponse
    {
        $url = $request->input('url');
        $metadata = $this->embedService->extractMetadata($url);

        $media = Media::create([
            'user_id' => $request->user()->id,
            'type' => 'embed',
            'provider' => $metadata['provider'],
            'url' => $url,
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'thumbnail_url' => $metadata['thumbnail_url'],
            'size_bytes' => 0, // Embeds have no storage cost
            'is_ppv' => $request->boolean('is_ppv'),
            'price_coins' => $request->boolean('is_ppv') ? $request->input('price_coins') : null,
            'access_level' => $request->input('access_level', 'free'),
            'required_plan_level' => $request->input('required_plan_level'),
        ]);

        return response()->json([
            'message' => 'Cinema embed created successfully',
            'data' => [
                'id' => $media->id,
                'title' => $media->title,
                'url' => $media->url,
                'embed_url' => $metadata['embed_url'],
                'thumbnail_url' => $media->thumbnail_url,
                'provider' => $media->provider,
                'is_ppv' => $media->is_ppv,
                'price_coins' => $media->price_coins,
            ],
        ], 201);
    }

    /**
     * Delete a cinema embed.
     *
     * DELETE /api/v1/cinema/{media}
     */
    public function destroy(Media $media): JsonResponse
    {
        if ($media->type !== 'embed') {
            return response()->json(['message' => 'Not found'], 404);
        }

        if (Gate::denies('delete', $media)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $media->delete();

        return response()->json([
            'message' => 'Cinema embed deleted successfully',
        ]);
    }
}
