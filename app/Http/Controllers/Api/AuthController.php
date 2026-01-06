<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\Identity\Actions\LoginUserAction;
use App\Domains\Identity\Actions\LogoutUserAction;
use App\Domains\Identity\Actions\RegisterUserAction;
use App\Domains\Identity\DTOs\LoginUserDTO;
use App\Domains\Identity\DTOs\RegisterUserDTO;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Resources\UserResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(
        RegisterRequest $request,
        RegisterUserAction $action,
    ): JsonResponse {
        $user = $action->execute(
            RegisterUserDTO::fromArray($request->validated())
        );

        $token = $user->createToken(
            name: $request->validated('device_name', 'web'),
        )->plainTextToken;

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
            ],
            'message' => 'Registration successful',
        ], 201);
    }

    /**
     * Login user and return token.
     */
    public function login(
        LoginRequest $request,
        LoginUserAction $action,
    ): JsonResponse {
        $result = $action->execute(
            LoginUserDTO::fromArray($request->validated())
        );

        return response()->json([
            'data' => [
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
            ],
            'message' => 'Login successful',
        ]);
    }

    /**
     * Logout current user.
     */
    public function logout(
        Request $request,
        LogoutUserAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $action->execute(
            $user,
            allDevices: $request->boolean('all_devices', false),
        );

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get current authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load(['profile.originLocation', 'profile.currentLocation', 'wallet']);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }
}
