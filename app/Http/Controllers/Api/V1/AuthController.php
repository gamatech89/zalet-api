<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\FounderVerificationService;
use Carbon\Carbon;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

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
            'registration_ip' => $request->ip(),
            'last_ip' => $request->ip(),
        ]);

        // Create empty profile for the user
        $user->profile()->create([]);

        // Auto-assign Free plan subscription
        $freePlan = SubscriptionPlan::where('slug', 'free')->where('level', 0)->first();
        if ($freePlan) {
            Subscription::create([
                'user_id'              => $user->id,
                'subscription_plan_id' => $freePlan->id,
                'billing_cycle'        => 'monthly',
                'price_paid'           => 0,
                'starts_at'            => Carbon::now(),
                'ends_at'              => Carbon::now()->addYears(100),
                'status'               => 'active',
                'auto_renew'           => false,
            ]);
        }

        // Send email verification notification
        $user->sendEmailVerificationNotification();

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

        if ($user->isSuspended()) {
            $until = $user->suspended_until->format('d.m.Y H:i');
            return response()->json([
                'message' => "Nalog je privremeno suspendovan do {$until}.",
                'suspended_until' => $user->suspended_until->toIso8601String(),
                'suspension_reason' => $user->suspension_reason,
            ], 403);
        }

        $user->update(['last_ip' => $request->ip()]);

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
     * Verify email address via signed link from email.
     * GET /api/v1/auth/verify-email/{id}/{hash}
     */
    public function verifyEmail(Request $request, string $id, string $hash): \Illuminate\Http\RedirectResponse
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        $user = User::findOrFail($id);

        if (!hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return redirect("{$frontendUrl}/verify-email?error=invalid");
        }

        if ($user->hasVerifiedEmail()) {
            return redirect("{$frontendUrl}/verify-email?already=1");
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return redirect("{$frontendUrl}/verify-email?success=1");
    }

    /**
     * Resend email verification notification.
     * POST /api/v1/auth/verify-email/resend
     */
    public function resendVerification(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email je već verifikovan.'], 422);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verifikacioni email je ponovo poslat.']);
    }

    /**
     * Send a password reset link to the given email.
     *
     * POST /api/v1/auth/forgot-password
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        // Always return success — don't leak whether the email exists
        return response()->json([
            'message' => 'If an account with that email exists, a reset link has been sent.',
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
