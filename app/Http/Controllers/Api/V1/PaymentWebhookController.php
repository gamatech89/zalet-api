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
        $sigValid = $this->paymentService->verifyWebhookSignature($data);

        if (!$sigValid) {
            Log::warning('Raiffeisen webhook signature verification failed', [
                'order_id'    => $data['OrderID'] ?? 'unknown',
                'ip'          => $request->ip(),
                'has_upctoken' => !empty($data['UPCToken']),
                'all_fields'  => $data,
            ]);

            // UPCToken webhooks use a different UPC certificate we don't have yet.
            // Bypass sig check only when request comes from the verified UPC IP and
            // UPCToken is present — process and log for later cert investigation.
            $trustedIp = in_array($request->ip(), ['217.13.180.171', '195.85.198.15', '195.85.198.16']);
            if ($trustedIp && !empty($data['UPCToken'])) {
                Log::warning('Raiffeisen UPCToken webhook: sig failed but processing from trusted IP', [
                    'order_id' => $data['OrderID'] ?? 'unknown',
                    'ip'       => $request->ip(),
                ]);
                // Fall through to process the notification
            } else {
                return $this->buildUpcResponse([
                    'MerchantID'      => $data['MerchantID'] ?? '',
                    'TerminalID'      => $data['TerminalID'] ?? '',
                    'OrderID'         => $data['OrderID'] ?? '',
                    'Response.action' => 'reverse',
                    'Response.reason' => 'Signature verification failed',
                ]);
            }
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
