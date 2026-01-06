<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\Identity\Models\User;
use App\Domains\Wallet\Actions\GetPaymentIntentAction;
use App\Domains\Wallet\Actions\InitiateCreditPurchaseAction;
use App\Domains\Wallet\Resources\CreditPackageResource;
use App\Domains\Wallet\Resources\PaymentIntentResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PurchaseController extends Controller
{
    /**
     * Get available credit packages.
     */
    public function packages(): JsonResponse
    {
        $packages = config('services.credit_packages', []);

        return response()->json([
            'data' => CreditPackageResource::collection($packages),
        ]);
    }

    /**
     * Initiate a credit purchase.
     */
    public function initiate(
        Request $request,
        InitiateCreditPurchaseAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'package_id' => ['required', 'string', 'in:starter,popular,premium'],
            'language' => ['nullable', 'string', 'in:sr,en,de'],
        ]);

        try {
            $result = $action->execute(
                user: $user,
                packageId: $validated['package_id'],
                language: $validated['language'] ?? 'en',
            );

            return response()->json([
                'data' => [
                    'intent' => new PaymentIntentResource($result['intent']),
                    'paymentUrl' => $result['paymentUrl'],
                ],
                'message' => 'Payment initiated. Redirect to paymentUrl to complete.',
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get payment intent status.
     */
    public function status(
        Request $request,
        string $uuid,
        GetPaymentIntentAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $intent = $action->execute($uuid, $user);

        if ($intent === null) {
            return response()->json([
                'message' => 'Payment intent not found',
            ], 404);
        }

        return response()->json([
            'data' => new PaymentIntentResource($intent),
        ]);
    }

    /**
     * Get user's purchase history.
     */
    public function history(
        Request $request,
        GetPaymentIntentAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $status = $request->query('status');
        $status = is_string($status) ? $status : null;

        $intents = $action->forUser($user, $status, 20);

        return response()->json([
            'data' => PaymentIntentResource::collection($intents),
        ]);
    }
}
