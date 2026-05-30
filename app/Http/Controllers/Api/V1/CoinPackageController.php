<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CoinPackageController extends Controller
{
    /**
     * List available coin packages for purchase.
     *
     * GET /api/v1/coin-packages
     */
    public function index(): JsonResponse
    {
        $packages = config('zalet.coin_packages', []);

        return response()->json(['data' => $packages]);
    }
}
