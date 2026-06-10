<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\RaiffeisenPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentCallbackController extends Controller
{
    public function __construct(
        protected RaiffeisenPaymentService $paymentService,
    ) {}

    /**
     * Handle successful payment redirect from Raiffeisen.
     *
     * The bank redirects the user here after a successful payment.
     * Per Raiffeisen docs, UPCToken for card-on-file may be returned here
     * (via browser redirect) rather than via NOTIFY_URL — so we save it here too.
     */
    public function success(Request $request)
    {
        $data = $request->all();
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3100'));

        // Save UPCToken if Raiffeisen returns it via SUCCESS_URL redirect
        if (!empty($data['UPCToken']) && !empty($data['OrderID'])) {
            $transaction = Transaction::where('raiffeisen_order_id', $data['OrderID'])
                ->where('type', 'deposit')
                ->first();

            if ($transaction) {
                $userId = $transaction->toWallet?->user_id ?? '';
                if ($userId) {
                    $this->paymentService->saveCardFromWebhook($data, $userId);
                    Log::info('UPCToken saved from SUCCESS_URL callback', [
                        'order_id' => $data['OrderID'],
                        'user_id' => $userId,
                    ]);
                }
            }
        }

        return redirect("{$frontendUrl}/wallet?payment=success");
    }

    /**
     * Handle failed payment redirect from Raiffeisen.
     */
    public function failure(Request $request)
    {
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3100'));
        
        return redirect("{$frontendUrl}/wallet?payment=failed");
    }
}
