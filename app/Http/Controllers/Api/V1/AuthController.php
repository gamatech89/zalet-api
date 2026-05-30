<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\FounderVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        protected FounderVerificationService $founderService
    ) {}

    /**
     * Register a new user.
     *
     * POST /api/v1/auth/register
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'email' => $request->email,
            'username' => $request->username,
            'password' => $request->password,
            'role' => 'user',
        ]);

        // Create empty profile for the user
        $user->profile()->create([]);

        // Process legacy founder status (sets flag + credits bonus if applicable)
        $isFounder = $this->founderService->processRegistration($user);

        // Refresh user to get updated founder status
        $user->refresh();

        // Create Sanctum token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'user' => $user->load('profile', 'wallet'),
            'token' => $token,
            'is_legacy_founder' => $isFounder,
        ], 201);
    }

    /**
     * Login an existing user.
     *
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        // Revoke previous tokens (optional: single device login)
        // $user->tokens()->delete();

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user' => $user->load('profile', 'wallet'),
            'token' => $token,
        ]);
    }

    /**
     * Logout the current user.
     *
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke the current access token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Get the authenticated user.
     *
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->load('profile', 'wallet'),
        ]);
    }
}
