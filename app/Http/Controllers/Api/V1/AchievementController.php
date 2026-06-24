<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventType;
use App\Http\Controllers\Controller;
use App\Services\Achievements\Payloads\EventPayload;
use App\Http\Resources\AchievementResource;
use App\Http\Resources\AchievementTierResource;
use App\Models\Achievement;
use App\Models\AchievementTier;
use App\Rules\ResolvableJsonType;
use App\Services\AchievementService;
use App\Services\Achievements\Aggregations\Aggregation;
use App\Services\Achievements\Rewards\Reward;
use App\Support\JsonMapping\JsonTypeConverter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AchievementController extends Controller
{
    public function __construct(
        protected AchievementService $achievementService,
    ) {}

    // Admin

    public function index(): AnonymousResourceCollection
    {
        return AchievementResource::collection($this->achievementService->list());
    }

    public function show(Achievement $achievement): AchievementResource
    {
        return new AchievementResource($achievement->load('tiers'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'present|nullable|string',
            'icon' => 'present|nullable|string|max:255',
            'event_type' => ['required', Rule::enum(EventType::class)],
            'aggregation' => ['required', 'array', new ResolvableJsonType(Aggregation::class)],
            'tiers' => 'required|array|min:1',
            'tiers.*.threshold' => 'required|integer|min:1',
            'tiers.*.icon' => 'sometimes|nullable|string|max:255',
            'tiers.*.reward' => ['sometimes', 'nullable', 'array', new ResolvableJsonType(Reward::class)],
        ]);

        try {
            $achievement = $this->achievementService->create($validated);

            return (new AchievementResource($achievement))
                ->response()
                ->setStatusCode(201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, Achievement $achievement): AchievementResource|JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'icon' => 'sometimes|nullable|string|max:255',
            'event_type' => ['sometimes', Rule::enum(EventType::class)],
            'aggregation' => ['sometimes', 'array', new ResolvableJsonType(Aggregation::class)],
            'is_enabled' => 'sometimes|boolean',
            'tiers' => 'sometimes|array|min:1',
            'tiers.*.id' => 'sometimes|string',
            'tiers.*.threshold' => 'required_with:tiers|integer|min:1',
            'tiers.*.icon' => 'sometimes|nullable|string|max:255',
            'tiers.*.reward' => ['sometimes', 'nullable', 'array', new ResolvableJsonType(Reward::class)],
        ]);

        try {
            $achievement = $this->achievementService->update($achievement, $validated);

            return new AchievementResource($achievement);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function fields(Request $request): JsonResponse
    {
        $request->validate([
            'event_type' => ['required', Rule::enum(EventType::class)],
        ]);

        $eventType = EventType::from($request->input('event_type'));

        return response()->json([
            'data' => EventPayload::fieldsFor($eventType),
        ]);
    }

    public function destroy(Achievement $achievement): JsonResponse
    {
        $this->achievementService->delete($achievement);

        return response()->json(null, 204);
    }

    // User

    public function userAchievements(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->achievementService->getUserAchievements($request->user()),
        ]);
    }

    public function collect(Request $request, AchievementTier $achievementTier): JsonResponse
    {
        try {
            $reward = $this->achievementService->collect($request->user(), $achievementTier);

            return response()->json([
                'message' => 'Reward collected!',
                'reward' => JsonTypeConverter::toArray($reward),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
