<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\Identity\Actions\GetUserByUuidAction;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Enums\PostType;
use App\Domains\Streaming\Actions\CreatePostAction;
use App\Domains\Streaming\Actions\DeletePostAction;
use App\Domains\Streaming\Actions\GetFeedAction;
use App\Domains\Streaming\Actions\GetPostByUuidAction;
use App\Domains\Streaming\Actions\GetUserPostsAction;
use App\Domains\Streaming\Actions\UpdatePostAction;
use App\Domains\Streaming\Resources\PostResource;
use App\Domains\Streaming\Services\VideoParserService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PostController extends Controller
{
    public function __construct(
        private readonly GetPostByUuidAction $getPostByUuid,
        private readonly VideoParserService $videoParser,
    ) {}

    /**
     * Get the main feed.
     */
    public function feed(
        Request $request,
        GetFeedAction $action,
    ): JsonResponse {
        /** @var User|null $user */
        $user = $request->user();

        $type = null;
        $typeParam = $request->query('type');
        if ($typeParam !== null && is_string($typeParam)) {
            $type = PostType::tryFrom($typeParam);
        }

        $locationId = $request->query('location_id');
        $locationId = is_numeric($locationId) ? (int) $locationId : null;

        $posts = $action->execute(
            user: $user,
            type: $type,
            locationId: $locationId,
            perPage: min((int) $request->query('per_page', 20), 100),
        );

        return response()->json([
            'data' => PostResource::collection($posts->items()),
            'meta' => [
                'currentPage' => $posts->currentPage(),
                'lastPage' => $posts->lastPage(),
                'perPage' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    /**
     * Get a single post.
     */
    public function show(string $uuid): JsonResponse
    {
        $post = $this->getPostByUuid->execute($uuid);

        if (! $post || ! $post->is_published) {
            return response()->json(['message' => 'Post not found'], 404);
        }

        return response()->json([
            'data' => new PostResource($post),
        ]);
    }

    /**
     * Create a new post.
     */
    public function store(
        Request $request,
        CreatePostAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:video,short_clip,image'],
            'source_url' => ['required', 'url', 'max:2000'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_premium' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        // Validate video URL for video types
        $type = PostType::from($validated['type']);
        if ($type->supportsProvider()) {
            $validation = $this->videoParser->validate($validated['source_url']);
            if (! $validation['valid']) {
                return response()->json([
                    'message' => 'Invalid video URL',
                    'errors' => ['source_url' => [$validation['error'] ?? 'Unsupported video provider']],
                ], 422);
            }
        }

        $post = $action->execute($user, [
            'type' => $type,
            'source_url' => $validated['source_url'],
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_premium' => $validated['is_premium'] ?? false,
            'is_published' => $validated['is_published'] ?? true,
        ]);

        $post->load('user.profile');

        return response()->json([
            'data' => new PostResource($post),
            'message' => 'Post created successfully.',
        ], 201);
    }

    /**
     * Update an existing post.
     */
    public function update(
        Request $request,
        string $uuid,
        UpdatePostAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $post = $this->getPostByUuid->execute($uuid, false);

        if (! $post) {
            return response()->json(['message' => 'Post not found'], 404);
        }

        $validated = $request->validate([
            'source_url' => ['nullable', 'url', 'max:2000'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_premium' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        // Validate video URL if being updated
        if (isset($validated['source_url']) && $post->type->supportsProvider()) {
            $validation = $this->videoParser->validate($validated['source_url']);
            if (! $validation['valid']) {
                return response()->json([
                    'message' => 'Invalid video URL',
                    'errors' => ['source_url' => [$validation['error'] ?? 'Unsupported video provider']],
                ], 422);
            }
        }

        try {
            $post = $action->execute($user, $post, $validated);
            $post->load('user.profile');

            return response()->json([
                'data' => new PostResource($post),
                'message' => 'Post updated successfully.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    /**
     * Delete a post.
     */
    public function destroy(
        Request $request,
        string $uuid,
        DeletePostAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $post = $this->getPostByUuid->execute($uuid, false);

        if (! $post) {
            return response()->json(['message' => 'Post not found'], 404);
        }

        try {
            $action->execute($user, $post);

            return response()->json(['message' => 'Post deleted successfully.']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    /**
     * Get posts for a specific user.
     */
    public function userPosts(
        Request $request,
        string $uuid,
        GetUserPostsAction $action,
        GetUserByUuidAction $getUserByUuid,
    ): JsonResponse {
        $user = $getUserByUuid->execute($uuid, false);

        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $posts = $action->execute(
            user: $user,
            publishedOnly: true,
            perPage: min((int) $request->query('per_page', 20), 100),
        );

        return response()->json([
            'data' => PostResource::collection($posts->items()),
            'meta' => [
                'currentPage' => $posts->currentPage(),
                'lastPage' => $posts->lastPage(),
                'perPage' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }
}
