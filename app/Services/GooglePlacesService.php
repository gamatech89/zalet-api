<?php

namespace App\Services;

use App\Models\Place;
use App\Models\PlaceTranslation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GooglePlacesService
{
    private string $apiKey;
    private const AUTOCOMPLETE_URL = 'https://maps.googleapis.com/maps/api/place/autocomplete/json';
    private const DETAILS_URL = 'https://maps.googleapis.com/maps/api/place/details/json';

    public function __construct()
    {
        $this->apiKey = config('services.google.places_api_key', '');
    }

    /**
     * Search for cities via Google Places Autocomplete.
     * Returns an array of place predictions.
     */
    public function searchCities(string $query, ?string $locale = null): array
    {
        if (empty($this->apiKey)) {
            Log::warning('Google Places API key not configured');
            return [];
        }

        $cacheKey = 'google_places_' . md5($query . $locale);
        
        return Cache::remember($cacheKey, 3600, function () use ($query, $locale) {
            $params = [
                'input' => $query,
                'types' => '(cities)',
                'key' => $this->apiKey,
            ];

            if ($locale) {
                $params['language'] = $locale;
            }

            try {
                $response = Http::get(self::AUTOCOMPLETE_URL, $params);

                if (!$response->successful()) {
                    Log::error('Google Places API error', ['status' => $response->status()]);
                    return [];
                }

                $data = $response->json();

                if ($data['status'] !== 'OK' && $data['status'] !== 'ZERO_RESULTS') {
                    Log::error('Google Places API status', ['status' => $data['status']]);
                    return [];
                }

                return $data['predictions'] ?? [];
            } catch (\Exception $e) {
                Log::error('Google Places API exception', ['message' => $e->getMessage()]);
                return [];
            }
        });
    }

    /**
     * Get place details from Google and save to local DB.
     * Returns the local Place model or null.
     */
    public function getAndSavePlace(string $googlePlaceId, ?string $locale = null): ?Place
    {
        // Check if already saved
        $existing = Place::where('google_place_id', $googlePlaceId)->first();
        if ($existing) {
            return $existing;
        }

        if (empty($this->apiKey)) {
            return null;
        }

        try {
            $params = [
                'place_id' => $googlePlaceId,
                'fields' => 'place_id,name,address_components,geometry,formatted_address',
                'key' => $this->apiKey,
            ];

            if ($locale) {
                $params['language'] = $locale;
            }

            $response = Http::get(self::DETAILS_URL, $params);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            if ($data['status'] !== 'OK') {
                return null;
            }

            $result = $data['result'];
            $components = collect($result['address_components'] ?? []);

            // Extract structured data
            $cityName = $this->extractComponent($components, 'locality')
                ?? $this->extractComponent($components, 'administrative_area_level_2')
                ?? $result['name'];
            $region = $this->extractComponent($components, 'administrative_area_level_1');
            $countryCode = $this->extractComponentShort($components, 'country');

            $coordinates = null;
            if (isset($result['geometry']['location'])) {
                $coordinates = [
                    'lat' => $result['geometry']['location']['lat'],
                    'lng' => $result['geometry']['location']['lng'],
                ];
            }

            // Create the place
            $place = Place::create([
                'external_id' => 'google_' . $googlePlaceId,
                'google_place_id' => $googlePlaceId,
                'source' => 'google',
                'type' => 'city',
                'country_code' => $countryCode ? strtoupper($countryCode) : 'XX',
                'region' => $region,
                'coordinates' => $coordinates,
            ]);

            // Save English name
            PlaceTranslation::create([
                'place_id' => $place->id,
                'locale' => 'en',
                'name' => $cityName,
            ]);

            // If requesting a non-English locale, fetch the name in that locale too
            if ($locale && $locale !== 'en') {
                $localizedResponse = Http::get(self::DETAILS_URL, [
                    'place_id' => $googlePlaceId,
                    'fields' => 'name,address_components',
                    'language' => $locale,
                    'key' => $this->apiKey,
                ]);

                if ($localizedResponse->successful()) {
                    $localizedData = $localizedResponse->json();
                    if ($localizedData['status'] === 'OK') {
                        $localizedComponents = collect($localizedData['result']['address_components'] ?? []);
                        $localizedName = $this->extractComponent($localizedComponents, 'locality')
                            ?? $this->extractComponent($localizedComponents, 'administrative_area_level_2')
                            ?? $localizedData['result']['name'];

                        if ($localizedName !== $cityName) {
                            PlaceTranslation::create([
                                'place_id' => $place->id,
                                'locale' => $locale,
                                'name' => $localizedName,
                            ]);
                        }
                    }
                }
            }

            return $place->load('translations');
        } catch (\Exception $e) {
            Log::error('Google Places detail fetch failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Extract a component from Google address_components by type (long_name).
     */
    private function extractComponent($components, string $type): ?string
    {
        $component = $components->first(fn($c) => in_array($type, $c['types'] ?? []));
        return $component['long_name'] ?? null;
    }

    /**
     * Extract a component short_name (e.g. country code "RS").
     */
    private function extractComponentShort($components, string $type): ?string
    {
        $component = $components->first(fn($c) => in_array($type, $c['types'] ?? []));
        return $component['short_name'] ?? null;
    }
}
