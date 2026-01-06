<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\Wallet\Actions\ProcessPaymentWebhookAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class WebhookController extends Controller
{
    /**
     * Handle RaiAccept payment webhook.
     */
    public function raiaccept(
        Request $request,
        ProcessPaymentWebhookAction $action,
    ): JsonResponse {
        try {
            $action->execute($request);

            return response()->json([
                'status' => 'ok',
                'message' => 'Webhook processed successfully',
            ]);
        } catch (\RuntimeException $e) {
            Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
