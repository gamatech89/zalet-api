<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Wallet\Services\StubRaiAcceptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Controller for stub payment form (development only).
 */
final class StubPaymentController extends Controller
{
    /**
     * Show stub payment form.
     */
    public function form(Request $request, string $order): JsonResponse
    {
        if (config('services.raiaccept.mode') !== 'stub') {
            abort(404);
        }

        $orderData = StubRaiAcceptService::getStoredOrder($order);

        if ($orderData === null) {
            return response()->json([
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'data' => [
                'orderIdentification' => $orderData['orderIdentification'],
                'amount' => $orderData['amountCents'] / 100,
                'currency' => $orderData['currency'],
                'customerEmail' => $orderData['customerEmail'],
                'successUrl' => $orderData['successUrl'],
                'failureUrl' => $orderData['failureUrl'],
                'cancelUrl' => $orderData['cancelUrl'],
            ],
            'message' => 'Use POST /stub/payment/{order}/complete or /fail to simulate payment result',
        ]);
    }

    /**
     * Simulate successful payment.
     */
    public function complete(Request $request, string $order): JsonResponse
    {
        return $this->simulatePayment($order, true);
    }

    /**
     * Simulate failed payment.
     */
    public function fail(Request $request, string $order): JsonResponse
    {
        return $this->simulatePayment($order, false);
    }

    /**
     * Simulate payment and trigger webhook.
     */
    private function simulatePayment(string $order, bool $success): JsonResponse
    {
        if (config('services.raiaccept.mode') !== 'stub') {
            abort(404);
        }

        $service = app(StubRaiAcceptService::class);
        $result = $service->simulatePayment($order, $success);

        // Trigger webhook internally
        $webhookUrl = $result['notificationUrl'];

        Log::info('Stub payment triggering webhook', [
            'order' => $order,
            'success' => $success,
            'webhook_url' => $webhookUrl,
        ]);

        // Send internal HTTP request to webhook endpoint
        try {
            $response = Http::post($webhookUrl, [
                'orderIdentification' => $result['orderIdentification'],
                'transactionId' => $result['transactionId'],
                'status' => $result['status'],
                'responseCode' => $result['responseCode'],
                'amountCents' => $result['amountCents'],
            ]);

            $webhookSuccess = $response->successful();
        } catch (\Exception $e) {
            Log::error('Failed to trigger stub webhook', [
                'error' => $e->getMessage(),
            ]);
            $webhookSuccess = false;
        }

        return response()->json([
            'data' => [
                'orderIdentification' => $result['orderIdentification'],
                'status' => $result['status'],
                'redirectUrl' => $result['redirectUrl'],
                'webhookTriggered' => $webhookSuccess,
            ],
            'message' => $success ? 'Payment completed successfully' : 'Payment failed',
        ]);
    }
}
