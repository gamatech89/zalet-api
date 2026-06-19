<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CoinPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoinPackageController extends Controller
{
    /**
     * List active coin packages for purchase.
     * GET /api/v1/coin-packages
     */
    public function index(): JsonResponse
    {
        $packages = CoinPackage::active()
            ->orderBy('sort_order')
            ->get()
            ->map(fn($p) => $this->format($p));

        return response()->json(['data' => $packages]);
    }

    /**
     * Admin: list all packages (including inactive).
     * GET /api/v1/admin/coin-packages
     */
    public function adminIndex(): JsonResponse
    {
        $packages = CoinPackage::orderBy('sort_order')->get()->map(fn($p) => $this->format($p));

        return response()->json(['data' => $packages]);
    }

    /**
     * Admin: create a new package.
     * POST /api/v1/admin/coin-packages
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'coins'      => 'required|integer|min:1',
            'bonus'      => 'integer|min:0',
            'price_rsd'  => 'required|integer|min:1',
            'label'      => 'nullable|string|max:50',
            'is_active'  => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $package = CoinPackage::create($data);

        return response()->json(['data' => $this->format($package)], 201);
    }

    /**
     * Admin: update a package.
     * PUT /api/v1/admin/coin-packages/{package}
     */
    public function update(Request $request, CoinPackage $package): JsonResponse
    {
        $data = $request->validate([
            'coins'      => 'integer|min:1',
            'bonus'      => 'integer|min:0',
            'price_rsd'  => 'integer|min:1',
            'label'      => 'nullable|string|max:50',
            'is_active'  => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $package->update($data);

        return response()->json(['data' => $this->format($package->fresh())]);
    }

    /**
     * Admin: delete a package.
     * DELETE /api/v1/admin/coin-packages/{package}
     */
    public function destroy(CoinPackage $package): JsonResponse
    {
        $package->delete();

        return response()->json(['message' => 'Package deleted.']);
    }

    private function format(CoinPackage $p): array
    {
        return [
            'id'         => $p->id,
            'coins'      => $p->coins,
            'bonus'      => $p->bonus,
            'price_rsd'  => $p->price_rsd,
            'label'      => $p->label,
            'is_active'  => $p->is_active,
            'sort_order' => $p->sort_order,
        ];
    }
}
