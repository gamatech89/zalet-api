<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\Identity\Actions\GetUserByUuidAction;
use App\Domains\Identity\Actions\GetUsersByLocationAction;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Resources\UserResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserController extends Controller
{
    /**
     * Get a user's public profile by UUID.
     */
    public function show(
        string $uuid,
        GetUserByUuidAction $action,
    ): JsonResponse {
        $user = $action->execute($uuid);

        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Get users by location (origin or current).
     */
    public function byLocation(
        Request $request,
        int $locationId,
        GetUsersByLocationAction $action,
    ): JsonResponse {
        // Check if location exists
        if (! Location::where('id', $locationId)->exists()) {
            return response()->json(['message' => 'Location not found'], 404);
        }

        /** @var User|null $currentUser */
        $currentUser = $request->user();

        $locationType = $request->query('type', 'origin');
        if (! in_array($locationType, ['origin', 'current'], true)) {
            $locationType = 'origin';
        }

        $users = $action->execute(
            locationId: $locationId,
            locationType: $locationType,
            excludeUser: $currentUser,
            perPage: min((int) $request->query('per_page', 20), 100),
        );

        return response()->json([
            'data' => UserResource::collection($users->items()),
            'meta' => [
                'currentPage' => $users->currentPage(),
                'lastPage' => $users->lastPage(),
                'perPage' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }
}
