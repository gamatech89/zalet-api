<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\Identity\Actions\SearchLocationsAction;
use App\Domains\Identity\Resources\LocationResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LocationController extends Controller
{
    /**
     * Search locations by city or country.
     */
    public function search(
        Request $request,
        SearchLocationsAction $action,
    ): JsonResponse {
        $locations = $action->execute(
            query: $request->query('q'),
            countryCode: $request->query('country_code'),
            perPage: min((int) $request->query('per_page', 20), 100),
        );

        return response()->json([
            'data' => LocationResource::collection($locations->items()),
            'meta' => [
                'currentPage' => $locations->currentPage(),
                'lastPage' => $locations->lastPage(),
                'perPage' => $locations->perPage(),
                'total' => $locations->total(),
            ],
        ]);
    }
}
