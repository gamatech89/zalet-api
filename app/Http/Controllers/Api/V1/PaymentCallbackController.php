<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PaymentCallbackController extends Controller
{
    /**
     * Handle successful payment redirect from Raiffeisen.
     *
     * The bank redirects the user here (via POST or GET) after a successful payment.
     * We redirect them back to the frontend with a success indicator.
     * The actual payment confirmation/coin crediting happens via the webhook (NOTIFY_URL).
     */
    public function success(Request $request)
    {
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3100'));
        
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
