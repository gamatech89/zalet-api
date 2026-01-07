<?php

namespace App\Http\Controllers;

use App\Services\LevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LevelController extends Controller
{
    public function __construct(
        private LevelService $levelService
    ) {}

    /**
     * Get current user's level info
     */
    public function me(Request $request): JsonResponse
    {
        $levelInfo = $this->levelService->getLevelInfo($request->user());

        return response()->json([
            'data' => $levelInfo,
        ]);
    }

    /**
     * Get all tiers (for display purposes)
     */
    public function tiers(): JsonResponse
    {
        return response()->json([
            'data' => $this->levelService->getAllTiers(),
        ]);
    }

    /**
     * Get bar perks by level
     */
    public function barPerks(): JsonResponse
    {
        return response()->json([
            'data' => $this->levelService->getAllBarPerks(),
        ]);
    }

    /**
     * Check if user can create a bar
     */
    public function canCreateBar(Request $request): JsonResponse
    {
        $result = $this->levelService->canCreateBar($request->user());

        return response()->json([
            'data' => $result,
        ]);
    }
}
