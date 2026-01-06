<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\Identity\Actions\UpdateProfileAction;
use App\Domains\Identity\DTOs\UpdateProfileDTO;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Resources\ProfileResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProfileController extends Controller
{
    /**
     * Get current user's profile.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load(['profile.originLocation', 'profile.currentLocation']);

        return response()->json([
            'data' => new ProfileResource($user->profile),
        ]);
    }

    /**
     * Update current user's profile.
     */
    public function update(
        UpdateProfileRequest $request,
        UpdateProfileAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $profile = $action->execute(
            $user,
            UpdateProfileDTO::fromArray($request->validated())
        );

        return response()->json([
            'data' => new ProfileResource($profile),
            'message' => 'Profile updated successfully',
        ]);
    }
}
