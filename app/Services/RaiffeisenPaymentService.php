<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RaiffeisenPaymentService
{
    protected string $merchantId;
    protected string $terminalId;
    protected string $gatewayUrl;
    protected string $successUrl;
    protected string $failureUrl;

    public function __construct(
        protected CoinService $coinService,
        protected ?GlobalSubscriptionService $subscriptionService = null,
    ) {
        $this->subscriptionService = $subscriptionService ?? app(GlobalSubscriptionService::class);
        $this->merchantId = config('zalet.raiffeisen.merchant_id');
        $this->terminalId = config('zalet.raiffeisen.terminal_id');
        $this->gatewayUrl = config('zalet.raiffeisen.gateway_url');
        $this->successUrl = config('zalet.raiffeisen.success_url');
        $this->failureUrl = config('zalet.raiffeisen.failure_url');
    }

    /**
     * Create a payment order and return the payment page URL.
     *
     * @param User $user The user making the deposit
     * @param float $amountRsd Amount in RSD (Serbian Dinars)
     * @return array Payment data including URL and order ID
     */
    public function createPaymentOrder(User $user, float $amountRsd): array
    {
        // Generate unique order ID (required by UPC gateway)
        $orderId = $this->generateOrderId();
        
        // Convert to smallest unit (paras/cents) - UPC expects amount in smallest currency unit
        $amountInParas = (int) round($amountRsd * 100);
        
        // Create pending deposit transaction
        $transaction = $this->coinService->deposit(
            user: $user,
            amount: $this->convertRsdToCoins($amountRsd),
            raiffeisenOrderId: $orderId,
        );

        // Build payment URL with parameters
        $paymentUrl = $this->buildPaymentUrl([
            'MerchantID' => $this->merchantId,
            'TerminalID' => $this->terminalId,
            'TotalAmount' => $amountInParas,
            'Currency' => '941', // RSD currency code
            'locale' => 'sr', // Serbian language
            'OrderID' => $orderId,
            'PurchaseTime' => now()->format('ymdHis'),
            'SD' => $this->generateSessionData($orderId, $amountInParas),
        ]);

        Log::info('Payment order created', [
            'user_id' => $user->id,
            'order_id' => $orderId,
            'amount_rsd' => $amountRsd,
            'transaction_id' => $transaction->id,
        ]);

        return [
            'payment_url' => $paymentUrl,
            'order_id' => $orderId,
            'transaction_id' => $transaction->id,
        ];
    }

    /**
     * Create a subscription payment order and return the payment page URL.
     *
     * @param User $user The user subscribing
     * @param SubscriptionPlan $plan The plan to subscribe to
     * @param string $billingCycle 'monthly' or 'yearly'
     * @return array Payment data including URL and order ID
     */
    public function createSubscriptionPayment(
        User $user,
        SubscriptionPlan $plan,
        string $billingCycle
    ): array {
        // Generate unique order ID with subscription prefix
        $orderId = $this->generateSubscriptionOrderId();

        // Calculate price
        $priceRsd = $this->subscriptionService->calculatePrice($plan, $billingCycle);
        $amountInParas = (int) round($priceRsd * 100);

        // Build payment URL
        $paymentUrl = $this->buildPaymentUrl([
            'MerchantID' => $this->merchantId,
            'TerminalID' => $this->terminalId,
            'TotalAmount' => $amountInParas,
            'Currency' => '941', // RSD
            'locale' => 'sr',
            'OrderID' => $orderId,
            'PurchaseTime' => now()->format('ymdHis'),
            'SD' => $this->generateSubscriptionSessionData(
                $orderId, $amountInParas, $user->id, $plan->id, $billingCycle
            ),
        ]);

        Log::info('Subscription payment order created', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'order_id' => $orderId,
            'amount_rsd' => $priceRsd,
            'billing_cycle' => $billingCycle,
        ]);

        return [
            'payment_url' => $paymentUrl,
            'order_id' => $orderId,
            'plan_id' => $plan->id,
            'amount' => $priceRsd,
            'currency' => 'RSD',
            'billing_cycle' => $billingCycle,
        ];
    }

    /**
     * Create a tokenized payment using a saved card (card on file).
     *
     * Per Raiffeisen spec: Token payments use JWS (JSON Web Signature, RFC 7515)
     * format and POST to /payByToken endpoint, NOT the regular /enter endpoint.
     *
     * @param User $user The user making the payment
     * @param float $amountRsd Amount in RSD
     * @param PaymentMethod $paymentMethod The saved payment method with UPCToken
     * @return array Payment result with order_id and transaction_id
     */
    public function createTokenizedPayment(
        User $user,
        float $amountRsd,
        PaymentMethod $paymentMethod
    ): array {
        $orderId = $this->generateOrderId();
        $amountInParas = (int) round($amountRsd * 100);
        $purchaseTime = now()->format('ymdHis');

        // Create pending deposit transaction
        $transaction = $this->coinService->deposit(
            user: $user,
            amount: $this->convertRsdToCoins($amountRsd),
            raiffeisenOrderId: $orderId,
        );

        // Build JWS payload per Raiffeisen tokenization spec
        $payload = [
            'MerchantID' => $this->merchantId,
            'TerminalID' => $this->terminalId,
            'OrderID' => $orderId,
            'UPCToken' => $paymentMethod->gateway_token,
            'TotalAmount' => $amountInParas,
            'Currency' => 941,
            'PurchaseTime' => $purchaseTime,
            'PurchaseDesc' => 'ZaletCoin deposit',
        ];

        // JWS header (RS256 algorithm)
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256']));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

        // Sign header.payload with merchant private key
        $dataToSign = "{$header}.{$payloadEncoded}";
        $privateKey = openssl_pkey_get_private(
            file_get_contents(config('zalet.raiffeisen.pem_path'))
        );
        openssl_sign($dataToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $signatureEncoded = $this->base64UrlEncode($signature);

        // POST JWS to payByToken endpoint
        $tokenPaymentUrl = str_replace('/enter', '/payByToken', $this->gatewayUrl);

        try {
            $response = \Illuminate\Support\Facades\Http::post($tokenPaymentUrl, [
                'header' => $header,
                'payload' => $payloadEncoded,
                'signature' => $signatureEncoded,
            ]);

            $result = $response->json();

            Log::info('Tokenized payment submitted', [
                'user_id' => $user->id,
                'order_id' => $orderId,
                'amount_rsd' => $amountRsd,
                'payment_method_id' => $paymentMethod->id,
                'card_last_four' => $paymentMethod->last_four,
                'response_tran_code' => $result['TranCode'] ?? 'unknown',
            ]);

            // Check if token payment was immediately approved
            if (($result['TranCode'] ?? '') === '000') {
                $this->coinService->confirmDeposit($transaction);
            }

        } catch (\Exception $e) {
            Log::error('Tokenized payment failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'order_id' => $orderId,
            'transaction_id' => $transaction->id,
        ];
    }

    /**
     * Base64URL encode (per RFC 7515 for JWS).
     */
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Verify the webhook signature from Raiffeisen.
     *
     * @param array $data POST data from webhook
     * @return bool True if signature is valid
     */
    public function verifyWebhookSignature(array $data): bool
    {
        $signature = base64_decode($data['Signature'] ?? '');
        
        if (empty($signature)) {
            return false;
        }

        // Build the data string for verification (based on notify.php reference)
        // When Delay is not present in POST, omit the ",Delay" suffix from OrderID
        $orderIdPart = isset($data['Delay'])
            ? ($data['OrderID'] ?? '') . ',' . $data['Delay']
            : ($data['OrderID'] ?? '');

        if (!empty($data['AltTotalAmount'])) {
            $dataString = implode(';', [
                $data['MerchantID'] ?? '',
                $data['TerminalID'] ?? '',
                $data['PurchaseTime'] ?? '',
                $orderIdPart,
                $data['XID'] ?? '',
                ($data['Currency'] ?? '') . ',' . ($data['AltCurrency'] ?? ''),
                ($data['TotalAmount'] ?? '') . ',' . ($data['AltTotalAmount'] ?? ''),
                $data['SD'] ?? '',
                $data['TranCode'] ?? '',
                $data['ApprovalCode'] ?? '',
            ]) . ';';
        } else {
            $dataString = implode(';', [
                $data['MerchantID'] ?? '',
                $data['TerminalID'] ?? '',
                $data['PurchaseTime'] ?? '',
                $orderIdPart,
                $data['XID'] ?? '',
                $data['Currency'] ?? '',
                $data['TotalAmount'] ?? '',
                $data['SD'] ?? '',
                $data['TranCode'] ?? '',
                $data['ApprovalCode'] ?? '',
            ]) . ';';
        }

        Log::debug('Raiffeisen webhook verification data', [
            'received_fields' => array_keys($data),
            'delay_value' => $data['Delay'] ?? 'NOT_PRESENT',
            'data_string' => $dataString,
            'data_string_length' => strlen($dataString),
            'signature_b64_length' => strlen($data['Signature'] ?? ''),
        ]);

        // Load the UPC certificate
        $certPath = config('zalet.raiffeisen.certificate_path');
        
        if (!file_exists($certPath)) {
            Log::error('Raiffeisen certificate not found', ['path' => $certPath]);
            return false;
        }

        $publicKey = openssl_pkey_get_public(file_get_contents($certPath));
        
        if (!$publicKey) {
            Log::error('Failed to load Raiffeisen certificate');
            return false;
        }

        $verifyResult = openssl_verify($dataString, $signature, $publicKey);

        if (PHP_VERSION_ID >= 80000) {
            // PHP 8.0+ doesn't need openssl_free_key
        } else {
            openssl_free_key($publicKey);
        }

        return $verifyResult === 1;
    }

    /**
     * Process a webhook notification from Raiffeisen.
     *
     * @param array $data POST data from webhook
     * @return array Response data
     */
    public function processWebhookNotification(array $data): array
    {
        $orderId = $data['OrderID'] ?? null;
        $tranCode = $data['TranCode'] ?? null;
        $approvalCode = $data['ApprovalCode'] ?? null;

        if (!$orderId) {
            return $this->buildWebhookResponse($data, 'reverse', 'Missing OrderID');
        }

        // Find the pending transaction
        $transaction = Transaction::where('raiffeisen_order_id', $orderId)
            ->where('type', 'deposit')
            ->where('status', 'pending')
            ->first();

        if (!$transaction) {
            Log::warning('Transaction not found for webhook', ['order_id' => $orderId]);
            return $this->buildWebhookResponse($data, 'reverse', 'Transaction not found');
        }

        // Check if payment was successful
        // TranCode '000' typically means success in UPC gateway
        if ($tranCode === '000' && !empty($approvalCode)) {
            // Determine if this is a subscription payment or coin deposit
            if ($this->isSubscriptionPayment($orderId)) {
                return $this->processSubscriptionPayment($data, $orderId);
            }

            // Coin deposit flow
            $this->coinService->confirmDeposit($transaction);

            // Auto-save card if UPCToken is present in response
            if (!empty($data['UPCToken'])) {
                $this->saveCardFromWebhook($data, $transaction->toWallet?->user_id ?? '');
            }

            Log::info('Deposit confirmed via webhook', [
                'order_id' => $orderId,
                'transaction_id' => $transaction->id,
                'approval_code' => $approvalCode,
                'has_token' => !empty($data['UPCToken']),
            ]);

            return $this->buildWebhookResponse($data, 'approve', 'ok');
        }

        // Payment failed
        if ($transaction) {
            $transaction->markFailed();
        }

        Log::warning('Payment failed', [
            'order_id' => $orderId,
            'tran_code' => $tranCode,
        ]);

        return $this->buildWebhookResponse($data, 'reverse', 'Payment declined');
    }

    /**
     * Save a card from webhook response data (for card-on-file).
     *
     * Per Raiffeisen spec, successful payments return:
     * - UPCToken: payment card digital token
     * - UPCTokenExp: token expiry (MMYYYY format)
     * - ProxyPan: masked card number (last 4 digits visible, zero-padded to PAN length)
     */
    public function saveCardFromWebhook(array $data, string $userId): ?PaymentMethod
    {
        $token = $data['UPCToken'] ?? null;
        $proxyPan = $data['ProxyPan'] ?? null;

        if (!$token || !$userId) {
            return null;
        }

        // Check if this token already exists for this user
        $existing = PaymentMethod::where('user_id', $userId)
            ->whereRaw("gateway_token = ?", [$token])
            ->first();

        if ($existing) {
            return $existing;
        }

        // Extract last 4 from ProxyPan (format: zero-padded, last 4 are real digits)
        $lastFour = $proxyPan ? substr($proxyPan, -4) : '0000';
        $firstDigit = $proxyPan ? substr($proxyPan, 0, 1) : '0';

        // Detect brand from first digit of PAN
        $brand = match ($firstDigit) {
            '4' => 'visa',
            '5' => 'mastercard',
            '3' => 'amex',
            '9' => 'dina',
            default => 'visa',
        };

        // Parse UPCTokenExp (MMYYYY -> MM and YY)
        $tokenExp = $data['UPCTokenExp'] ?? '';
        $expiryMonth = strlen($tokenExp) >= 2 ? substr($tokenExp, 0, 2) : '00';
        $expiryYear = strlen($tokenExp) >= 6 ? substr($tokenExp, 4, 2) : '00';

        $isFirst = PaymentMethod::where('user_id', $userId)->count() === 0;

        return PaymentMethod::create([
            'user_id' => $userId,
            'card_brand' => $brand,
            'last_four' => $lastFour,
            'expiry_month' => $expiryMonth,
            'expiry_year' => $expiryYear,
            'gateway_token' => $token,
            'is_default' => $isFirst,
        ]);
    }

    /**
     * Generate a unique order ID.
     */
    protected function generateOrderId(): string
    {
        // Format: ZALET-YYYYMMDD-XXXXX (max 20 chars for UPC gateway)
        return 'ZALET-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));
    }

    /**
     * Generate session data for UPC gateway.
     */
    protected function generateSessionData(string $orderId, int $amount): string
    {
        // SD (Session Data) is used for additional verification
        return base64_encode(json_encode([
            'o' => $orderId,
            'a' => $amount,
            't' => time(),
        ]));
    }

    /**
     * Build the payment redirect URL.
     * 
     * Returns a URL to our own intermediary page that auto-submits
     * a POST form to the Raiffeisen gateway (gateway requires POST, not GET).
     * 
     * In local environment, redirects to mock gateway for testing.
     */
    protected function buildPaymentUrl(array $params): string
    {
        // Add the signature to params
        $params['Signature'] = $this->generateSignature($params);
        $params['Version'] = '1';

        // In local env, use mock gateway for testing
        $targetGatewayUrl = app()->environment('local')
            ? url('/api/v1/mock-bank/pay')
            : $this->gatewayUrl;

        // Store params in cache for the intermediary page to pick up
        $cacheKey = 'payment_form_' . ($params['OrderID'] ?? Str::random(10));
        cache()->put($cacheKey, [
            'gateway_url' => $targetGatewayUrl,
            'params' => $params,
        ], now()->addMinutes(10));

        // Return URL to our intermediary form-submit page
        return url("/api/v1/payment/redirect/{$cacheKey}");
    }

    /**
     * Generate the digital signature for payment request.
     * 
     * Per Raiffeisen spec, the data string field order is:
     * MerchantId;TerminalId;PurchaseTime;OrderId;CurrencyId;Amount;;
     * 
     * The PHP example from the bank uses openssl_sign() without algorithm
     * (defaults to SHA1), matching their gateway verification.
     */
    protected function generateSignature(array $params): string
    {
        // Build data string per spec: fields separated by ;
        // Format: MerchantId;TerminalId;PurchaseTime;OrderId;CurrencyId;Amount;SD;
        $dataString = implode(';', [
            $params['MerchantID'],
            $params['TerminalID'],
            $params['PurchaseTime'],
            $params['OrderID'],
            $params['Currency'],
            $params['TotalAmount'],
            $params['SD'] ?? '',
            '', // trailing semicolon
        ]);

        $pemPath = config('zalet.raiffeisen.pem_path');

        if (!file_exists($pemPath)) {
            Log::error('Merchant PEM key not found', ['path' => $pemPath]);
            return '';
        }

        $privateKey = openssl_pkey_get_private(file_get_contents($pemPath));
        
        // Use SHA512 algorithm — confirmed by UPC/Raiffeisen tech support
        openssl_sign($dataString, $signature, $privateKey, OPENSSL_ALGO_SHA512);

        Log::debug('Raiffeisen signature generated', [
            'data_string' => $dataString,
            'data_string_length' => strlen($dataString),
            'signature_length' => strlen(base64_encode($signature)),
            'order_id' => $params['OrderID'] ?? 'unknown',
            'order_id_length' => strlen($params['OrderID'] ?? ''),
        ]);

        return base64_encode($signature);
    }

    /**
     * Convert RSD amount to ZaletCoins.
     * 
     * Exchange rate: 1 RSD = 1 ZaletCoin (for simplicity in MVP)
     */
    protected function convertRsdToCoins(float $amountRsd): float
    {
        $rate = config('zalet.exchange_rate.rsd_to_coin', 1.0);
        return round($amountRsd * $rate, 2);
    }

    /**
     * Build webhook response in UPC format.
     */
    protected function buildWebhookResponse(array $data, string $action, string $reason): array
    {
        return [
            'MerchantID' => $data['MerchantID'] ?? '',
            'TerminalID' => $data['TerminalID'] ?? '',
            'OrderID' => $data['OrderID'] ?? '',
            'Delay' => $data['Delay'] ?? '',
            'Currency' => $data['Currency'] ?? '',
            'TotalAmount' => $data['TotalAmount'] ?? '',
            'XID' => $data['XID'] ?? '',
            'PurchaseTime' => $data['PurchaseTime'] ?? '',
            'Response.action' => $action,
            'Response.reason' => $reason,
            'Response.forwardUrl' => $action === 'approve' ? $this->successUrl : $this->failureUrl,
        ];
    }

    // ── Subscription Payment Helpers ──

    /**
     * Generate unique order ID for subscriptions.
     */
    protected function generateSubscriptionOrderId(): string
    {
        // Format: ZSUB-YYYYMMDD-XXXXX (max 20 chars for UPC gateway)
        return 'ZSUB-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }

    /**
     * Check if an order ID is a subscription payment.
     */
    protected function isSubscriptionPayment(string $orderId): bool
    {
        return str_starts_with($orderId, 'ZSUB-');
    }

    /**
     * Generate session data for subscription payment.
     */
    protected function generateSubscriptionSessionData(
        string $orderId,
        int $amount,
        string $userId,
        string $planId,
        string $billingCycle
    ): string {
        return base64_encode(json_encode([
            'o' => $orderId,
            'a' => $amount,
            'u' => $userId,
            'p' => $planId,
            'c' => $billingCycle,
            't' => time(),
        ]));
    }

    /**
     * Process a confirmed subscription payment from webhook.
     */
    protected function processSubscriptionPayment(array $data, string $orderId): array
    {
        // Parse session data to get subscription details
        $sd = json_decode(base64_decode($data['SD'] ?? ''), true);

        if (!$sd || empty($sd['u']) || empty($sd['p'])) {
            Log::error('Invalid subscription session data', ['order_id' => $orderId]);
            return $this->buildWebhookResponse($data, 'reverse', 'Invalid session data');
        }

        $user = User::find($sd['u']);
        $plan = SubscriptionPlan::find($sd['p']);
        $billingCycle = $sd['c'] ?? 'monthly';
        $pricePaid = ($data['TotalAmount'] ?? 0) / 100; // Convert paras back to RSD

        if (!$user || !$plan) {
            Log::error('Subscription payment: user or plan not found', [
                'order_id' => $orderId,
                'user_id' => $sd['u'],
                'plan_id' => $sd['p'],
            ]);
            return $this->buildWebhookResponse($data, 'reverse', 'User or plan not found');
        }

        try {
            // Check if this is a renewal or new subscription
            $existing = Subscription::where('user_id', $user->id)
                ->whereIn('status', ['expired', 'cancelled'])
                ->latest()
                ->first();

            if ($existing) {
                $this->subscriptionService->renew($existing, $orderId, $pricePaid);
            } else {
                $this->subscriptionService->subscribe($user, $plan, $billingCycle, $orderId, $pricePaid);
            }

            Log::info('Subscription payment confirmed', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'order_id' => $orderId,
            ]);

            return $this->buildWebhookResponse($data, 'approve', 'ok');
        } catch (\Exception $e) {
            Log::error('Subscription payment processing failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            return $this->buildWebhookResponse($data, 'reverse', 'Processing failed');
        }
    }
}
