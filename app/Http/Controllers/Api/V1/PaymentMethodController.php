<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Services\RaiffeisenPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    /**
     * List the user's saved payment methods.
     *
     * GET /api/v1/payment-methods
     */
    public function index(Request $request): JsonResponse
    {
        $methods = PaymentMethod::where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($m) => $this->formatMethod($m));

        return response()->json(['data' => $methods]);
    }

    /**
     * Create a 1 RSD card registration payment via Raiffeisen to capture a new card token.
     *
     * POST /api/v1/payment-methods/add-card
     */
    public function addCard(Request $request): JsonResponse
    {
        $user = $request->user();

        if (PaymentMethod::where('user_id', $user->id)->count() >= 10) {
            return response()->json(['message' => 'Maximum of 10 payment methods allowed.'], 422);
        }

        $result = app(RaiffeisenPaymentService::class)->createCardRegistrationOrder($user);

        return response()->json([
            'payment_url' => $result['payment_url'],
            'order_id' => $result['order_id'],
        ]);
    }

    /**
     * Add a new payment method (card).
     *
     * POST /api/v1/payment-methods
     *
     * Typically called after a successful Raiffeisen payment returns a token,
     * or manually with card details from the form.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'card_brand' => ['required', 'string', 'in:visa,mastercard,dina,amex'],
            'last_four' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'expiry_month' => ['required', 'string', 'size:2', 'regex:/^(0[1-9]|1[0-2])$/'],
            'expiry_year' => ['required', 'string', 'size:2', 'regex:/^\d{2}$/'],
            'gateway_token' => ['required', 'string'],
            'label' => ['nullable', 'string', 'max:100'],
            'set_as_default' => ['boolean'],
        ]);

        $user = $request->user();

        // Check max cards limit (10 per user)
        $count = PaymentMethod::where('user_id', $user->id)->count();
        if ($count >= 10) {
            return response()->json([
                'message' => 'Maximum of 10 payment methods allowed.',
            ], 422);
        }

        $isFirstCard = $count === 0;

        $method = PaymentMethod::create([
            'user_id' => $user->id,
            'card_brand' => $validated['card_brand'],
            'last_four' => $validated['last_four'],
            'expiry_month' => $validated['expiry_month'],
            'expiry_year' => $validated['expiry_year'],
            'gateway_token' => $validated['gateway_token'],
            'label' => $validated['label'] ?? null,
            'is_default' => $isFirstCard, // Auto-default the first card
        ]);

        // If explicitly set as default, or first card
        if ($isFirstCard || ($validated['set_as_default'] ?? false)) {
            $method->makeDefault();
        }

        return response()->json([
            'message' => 'Payment method added.',
            'data' => $this->formatMethod($method),
        ], 201);
    }

    /**
     * Set a payment method as the default.
     *
     * PUT /api/v1/payment-methods/{paymentMethod}/default
     */
    public function setDefault(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        // Ensure ownership
        if ($paymentMethod->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $paymentMethod->makeDefault();

        return response()->json([
            'message' => 'Default payment method updated.',
            'data' => $this->formatMethod($paymentMethod->fresh()),
        ]);
    }

    /**
     * Delete all saved payment methods for the current user.
     *
     * DELETE /api/v1/payment-methods
     *
     * Used when a user unsubscribes and wants to remove all card data.
     * Since Raiffeisen/UPC has no token revocation API, deleting our local
     * records is the only way to ensure no future charges can be made.
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $deleted = PaymentMethod::where('user_id', $request->user()->id)->delete();

        return response()->json([
            'message' => 'All payment methods removed.',
            'deleted_count' => $deleted,
        ]);
    }

    /**
     * Delete a saved payment method.
     *
     * DELETE /api/v1/payment-methods/{paymentMethod}
     */
    public function destroy(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        // Ensure ownership
        if ($paymentMethod->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $wasDefault = $paymentMethod->is_default;
        $paymentMethod->delete();

        // If we deleted the default, promote the most recently added one
        if ($wasDefault) {
            $next = PaymentMethod::where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->first();

            $next?->makeDefault();
        }

        return response()->json([
            'message' => 'Payment method removed.',
        ]);
    }

    protected function formatMethod(PaymentMethod $method): array
    {
        return [
            'id' => $method->id,
            'card_brand' => $method->card_brand,
            'last_four' => $method->last_four,
            'expiry_month' => $method->expiry_month,
            'expiry_year' => $method->expiry_year,
            'is_default' => $method->is_default,
            'label' => $method->label,
            'display_name' => $method->displayName(),
            'is_expired' => $method->isExpired(),
            'created_at' => $method->created_at->toIso8601String(),
        ];
    }
}
