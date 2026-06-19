<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

/**
 * Renders an auto-submitting POST form to the Raiffeisen payment gateway.
 * 
 * The gateway only accepts POST requests, so we can't redirect with GET params.
 * Instead, we cache the form params and render this intermediary page that
 * auto-submits a POST form to the gateway URL.
 */
class PaymentRedirectController extends Controller
{
    public function show(string $cacheKey)
    {
        $data = cache()->get($cacheKey);

        if (!$data) {
            abort(410, 'Payment session expired. Please try again.');
        }

        // Delete after use (one-time)
        cache()->forget($cacheKey);

        $gatewayUrl = $data['gateway_url'];
        $params = $data['params'];

        $allowedHosts = ['ecommerce.raiffeisenbank.rs'];
        $host = parse_url($gatewayUrl, PHP_URL_HOST);
        if (!in_array($host, $allowedHosts, true)) {
            abort(400, 'Invalid payment gateway.');
        }

        // Build hidden input fields
        $inputs = '';
        foreach ($params as $key => $value) {
            $escaped = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            $inputs .= "<input type=\"hidden\" name=\"{$key}\" value=\"{$escaped}\">\n";
        }

        // Return auto-submitting HTML form
        return response("
<!DOCTYPE html>
<html>
<head>
    <title>Redirecting to payment...</title>
    <style>
        body {
            background: #0a0a0a;
            color: #ffffff;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            text-align: center;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(255,255,255,0.1);
            border-top-color: #ef4444;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        p { color: #999; font-size: 14px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='spinner'></div>
        <p>Redirecting to secure payment page...</p>
    </div>
    <form id='paymentForm' method='POST' action='{$gatewayUrl}'>
        {$inputs}
    </form>
    <script>document.getElementById('paymentForm').submit();</script>
</body>
</html>", 200, ['Content-Type' => 'text/html']);
    }
}
