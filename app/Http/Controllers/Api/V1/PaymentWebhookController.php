<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RaiffeisenPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(
        protected RaiffeisenPaymentService $paymentService,
    ) {}

    /**
     * Handle Raiffeisen payment webhook notification.
     * 
     * POST /api/v1/webhooks/raiffeisen
     * 
     * This endpoint receives payment confirmation callbacks from Raiffeisen UPC gateway.
     * The response format must follow UPC specification.
     */
    public function handleNotification(Request $request): Response
    {
        $data = $request->all();

        Log::info('Raiffeisen webhook received', [
            'order_id' => $data['OrderID'] ?? 'unknown',
            'ip' => $request->ip(),
        ]);

        // Verify the webhook signature
        if (!$this->paymentService->verifyWebhookSignature($data)) {
            // Log all incoming fields (except Signature value) so we can debug
            // what changed on UPC side (e.g. new fields added to signature string)
            Log::warning('Raiffeisen webhook signature verification failed', [
                'order_id'   => $data['OrderID'] ?? 'unknown',
                'ip'         => $request->ip(),
                'all_fields' => $data,
            ]);

            // Return response in UPC format even on failure
            return $this->buildUpcResponse([
                'MerchantID' => $data['MerchantID'] ?? '',
                'TerminalID' => $data['TerminalID'] ?? '',
                'OrderID' => $data['OrderID'] ?? '',
                'Response.action' => 'reverse',
                'Response.reason' => 'Signature verification failed',
            ]);
        }

        // Process the notification
        $response = $this->paymentService->processWebhookNotification($data);

        return $this->buildUpcResponse($response);
    }

    /**
     * Build response in UPC gateway expected format.
     * 
     * The UPC gateway expects a plain text response with key=value pairs.
     */
    protected function buildUpcResponse(array $data): Response
    {
        $lines = [];
        
        foreach ($data as $key => $value) {
            $lines[] = "{$key} = {$value}";
        }

        $content = implode("\n", $lines) . "\n";

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
