<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RaiffeisenPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Mock Raiffeisen payment gateway for local development.
 * 
 * Simulates the bank's hosted payment page so the full subscription
 * and deposit flows can be tested end-to-end without the real bank.
 * 
 * ONLY available when APP_ENV=local.
 */
class MockRaiffeisenController extends Controller
{
    public function __construct(
        protected RaiffeisenPaymentService $paymentService,
    ) {}

    /**
     * Show the fake bank payment form.
     * 
     * GET /api/v1/mock-bank/pay
     * 
     * Receives the same POST params that the real gateway would get.
     */
    public function showPaymentForm(Request $request): Response
    {
        if (app()->environment() !== 'local') {
            abort(404);
        }

        $orderId = $request->input('OrderID', '');
        $amount = $request->input('TotalAmount', '0');
        $merchantId = $request->input('MerchantID', '');
        $terminalId = $request->input('TerminalID', '');
        $currency = $request->input('Currency', '941');
        $sd = $request->input('SD', '');
        $purchaseTime = $request->input('PurchaseTime', '');
        $signature = $request->input('Signature', '');

        // Convert paras to RSD for display
        $amountRsd = number_format(intval($amount) / 100, 2, ',', '.');

        return response("
<!DOCTYPE html>
<html>
<head>
    <title>Mock Raiffeisen Payment</title>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 100%);
            color: #fff;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: #1e1e2e;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 32px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        .bank-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .bank-logo {
            font-size: 18px;
            font-weight: 800;
            color: #eab308;
            letter-spacing: 1px;
        }
        .mock-badge {
            background: #ef4444;
            color: white;
            font-size: 9px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .amount-display {
            text-align: center;
            margin-bottom: 24px;
        }
        .amount-label { font-size: 12px; color: #888; margin-bottom: 4px; }
        .amount-value { font-size: 36px; font-weight: 800; }
        .amount-currency { font-size: 14px; color: #888; margin-left: 6px; }
        .order-info { font-size: 11px; color: #666; margin-top: 4px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 12px; color: #888; margin-bottom: 6px; font-weight: 600; }
        input {
            width: 100%;
            padding: 12px 14px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 10px;
            color: #fff;
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s;
        }
        input:focus { border-color: #eab308; }
        input::placeholder { color: #555; }
        .row { display: flex; gap: 12px; }
        .row .form-group { flex: 1; }
        .btn-pay {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #eab308, #d4a00a);
            border: none;
            border-radius: 12px;
            color: #000;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 8px;
            transition: opacity 0.2s;
        }
        .btn-pay:hover { opacity: 0.9; }
        .btn-pay:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-cancel {
            width: 100%;
            padding: 12px;
            background: transparent;
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 12px;
            color: #888;
            font-size: 13px;
            cursor: pointer;
            margin-top: 8px;
        }
        .btn-cancel:hover { border-color: rgba(255,255,255,0.3); color: #bbb; }
        .secure-note {
            text-align: center;
            font-size: 10px;
            color: #555;
            margin-top: 16px;
        }
        .spinner { display: none; }
        .loading .spinner { display: inline-block; }
        .loading .btn-text { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .spin { animation: spin 0.8s linear infinite; display: inline-block; }
    </style>
</head>
<body>
    <div class='card'>
        <div class='bank-header'>
            <span class='bank-logo'>RAIFFEISEN</span>
            <span class='mock-badge'>Mock Gateway</span>
        </div>

        <div class='amount-display'>
            <div class='amount-label'>Iznos za plaćanje</div>
            <div class='amount-value'>{$amountRsd}<span class='amount-currency'>RSD</span></div>
            <div class='order-info'>Order: {$orderId}</div>
        </div>

        <form id='payForm' method='POST' action='/api/v1/mock-bank/process'>
            <input type='hidden' name='_token' value='" . csrf_token() . "'>
            <input type='hidden' name='OrderID' value='{$orderId}'>
            <input type='hidden' name='TotalAmount' value='{$amount}'>
            <input type='hidden' name='MerchantID' value='{$merchantId}'>
            <input type='hidden' name='TerminalID' value='{$terminalId}'>
            <input type='hidden' name='Currency' value='{$currency}'>
            <input type='hidden' name='SD' value='{$sd}'>
            <input type='hidden' name='PurchaseTime' value='{$purchaseTime}'>
            <input type='hidden' name='Signature' value='{$signature}'>

            <div class='form-group'>
                <label>Broj kartice</label>
                <input type='text' name='card_number' placeholder='4111 1111 1111 1111' value='4111111111111111' maxlength='19'>
            </div>

            <div class='row'>
                <div class='form-group'>
                    <label>Ističe</label>
                    <input type='text' name='card_expiry' placeholder='12/28' value='12/28' maxlength='5'>
                </div>
                <div class='form-group'>
                    <label>CVV</label>
                    <input type='text' name='card_cvv' placeholder='123' value='123' maxlength='4'>
                </div>
            </div>

            <div class='form-group'>
                <label>Ime na kartici</label>
                <input type='text' name='card_holder' placeholder='MARKO MARKOVIC' value='TEST USER'>
            </div>

            <button type='submit' class='btn-pay' id='payBtn'>
                <span class='btn-text'>Plati {$amountRsd} RSD</span>
                <span class='spinner'><span class='spin'>⏳</span> Obrađuje se...</span>
            </button>
        </form>

        <button class='btn-cancel' onclick=\"window.location.href='/subscriptions'\">Otkaži</button>

        <div class='secure-note'>🔒 Ovo je mock gateway za lokalno testiranje. U produkciji koristi se prava Raiffeisen banka.</div>
    </div>

    <script>
        document.getElementById('payForm').addEventListener('submit', function() {
            document.getElementById('payBtn').disabled = true;
            document.getElementById('payBtn').classList.add('loading');
        });
    </script>
</body>
</html>", 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Process the fake payment and trigger webhook internally.
     * 
     * POST /api/v1/mock-bank/process
     */
    public function processPayment(Request $request): \Illuminate\Http\RedirectResponse
    {
        if (app()->environment() !== 'local') {
            abort(404);
        }

        $orderId = $request->input('OrderID');
        $totalAmount = $request->input('TotalAmount');
        $merchantId = $request->input('MerchantID');
        $terminalId = $request->input('TerminalID');
        $currency = $request->input('Currency');
        $sd = $request->input('SD');
        $purchaseTime = $request->input('PurchaseTime');

        // Parse card info from form
        $cardNumber = preg_replace('/\s+/', '', $request->input('card_number', '4111111111111111'));
        $cardExpiry = $request->input('card_expiry', '12/28');
        $lastFour = substr($cardNumber, -4);
        $firstDigit = substr($cardNumber, 0, 1);

        // Generate fake UPC tokens
        $upcToken = 'MOCK_' . Str::random(32);
        $expiryParts = explode('/', $cardExpiry);
        $expiryMonth = $expiryParts[0] ?? '12';
        $expiryYear = $expiryParts[1] ?? '28';
        $upcTokenExp = $expiryMonth . '20' . $expiryYear; // MMYYYY format

        // Build a ProxyPan (zero-padded, last 4 are real digits like Raiffeisen returns)
        $proxyPan = str_pad('', 12, '0') . $lastFour;
        // Prefix with the first digit for brand detection
        $proxyPan = $firstDigit . substr($proxyPan, 1);

        Log::info('Mock payment processing', [
            'order_id' => $orderId,
            'amount' => $totalAmount,
            'card_last_four' => $lastFour,
        ]);

        // Simulate the webhook data that Raiffeisen would send
        $webhookData = [
            'MerchantID' => $merchantId,
            'TerminalID' => $terminalId,
            'OrderID' => $orderId,
            'TotalAmount' => $totalAmount,
            'Currency' => $currency,
            'PurchaseTime' => $purchaseTime,
            'SD' => $sd,
            'TranCode' => '000', // Success
            'ApprovalCode' => 'MOCK' . rand(100000, 999999),
            'XID' => Str::random(20),
            'Delay' => '0',
            'UPCToken' => $upcToken,
            'UPCTokenExp' => $upcTokenExp,
            'ProxyPan' => $proxyPan,
            'Signature' => '', // Skip signature verification for mock
        ];

        // Process the webhook internally (skip signature verification)
        $this->paymentService->processWebhookNotification($webhookData);

        // Also auto-save the card
        // Find the user from the transaction
        $transaction = \App\Models\Transaction::where('raiffeisen_order_id', $orderId)
            ->where('status', 'pending')
            ->first();

        if ($transaction) {
            $userId = $transaction->fromWallet?->user_id;
            if ($userId) {
                $this->paymentService->saveCardFromWebhook($webhookData, $userId);
            }
        }

        Log::info('Mock payment completed', [
            'order_id' => $orderId,
            'upc_token' => $upcToken,
        ]);

        // Redirect to frontend success page
        $frontendUrl = config('app.frontend_url', 'http://localhost:3100');
        return redirect("{$frontendUrl}/payment/success?order_id={$orderId}");
    }
}
