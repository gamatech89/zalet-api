<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Services\GooglePlacesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Search for locations by name (localized).
     * First searches local DB, falls back to Google Places if no results.
     * 
     * GET /api/v1/locations/search?q=Beč
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2'],
            'locale' => ['nullable', 'string', 'max:10'],
            'country' => ['nullable', 'string', 'max:2'],
        ]);

        $query = $request->input('q');
        $locale = $request->input('locale');

        // 1. Search local DB first
        $placesQuery = Place::search($query, $locale)
            ->with(['translations']);

        if ($request->filled('country')) {
            $placesQuery->where('country_code', strtoupper($request->input('country')));
        }

        $places = $placesQuery->limit(10)->get();

        // 2. If local results found, return them
        if ($places->isNotEmpty()) {
            return response()->json([
                'data' => $places->map(fn($place) => $this->formatPlace($place)),
                'source' => 'local',
            ]);
        }

        // 3. Fallback to Google Places API
        $googleService = app(GooglePlacesService::class);
        $predictions = $googleService->searchCities($query, $locale);

        if (empty($predictions)) {
            return response()->json(['data' => [], 'source' => 'none']);
        }

        $googleResults = collect($predictions)->map(function ($prediction) {
            // Extract country from structured_formatting or terms
            $terms = $prediction['terms'] ?? [];
            $countryCode = '';
            
            return [
                'id' => null,
                'google_place_id' => $prediction['place_id'],
                'name' => $prediction['structured_formatting']['main_text'] ?? $prediction['description'],
                'description' => $prediction['description'],
                'country_code' => $countryCode,
                'source' => 'google',
            ];
        })->take(10);

        return response()->json([
            'data' => $googleResults,
            'source' => 'google',
        ]);
    }

    /**
     * Save a Google Place to local DB (called when user selects a Google result).
     * 
     * POST /api/v1/locations/save-google-place
     */
    public function saveGooglePlace(Request $request): JsonResponse
    {
        $request->validate([
            'google_place_id' => ['required', 'string'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        $googleService = app(GooglePlacesService::class);
        $place = $googleService->getAndSavePlace(
            $request->input('google_place_id'),
            $request->input('locale', 'sr')
        );

        if (!$place) {
            return response()->json(['error' => 'Failed to save place'], 422);
        }

        $place->load('translations');

        return response()->json([
            'data' => $this->formatPlace($place),
        ]);
    }

    /**
     * Get place details.
     * 
     * GET /api/v1/locations/places/{place}
     */
    public function show(Place $place): JsonResponse
    {
        $place->load(['translations']);

        return response()->json([
            'data' => $this->formatPlace($place),
        ]);
    }

    /**
     * Format a Place model for API response.
     */
    private function formatPlace(Place $place): array
    {
        return [
            'id' => $place->id,
            'external_id' => $place->external_id,
            'google_place_id' => $place->google_place_id,
            'name' => $place->name,
            'country_code' => $place->country_code,
            'region' => $place->region,
            'coordinates' => $place->coordinates,
            'type' => $place->type,
            'source' => $place->source,
            'translations' => $place->translations->pluck('name', 'locale'),
        ];
    }
}
