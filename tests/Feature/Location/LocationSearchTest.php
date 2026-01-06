<?php

declare(strict_types=1);

use App\Domains\Identity\Models\Location;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    // Clear existing locations and seed test locations
    Location::query()->delete();
    Location::insert([
        ['city' => 'Beograd', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 44.8176, 'longitude' => 20.4633, 'created_at' => now()],
        ['city' => 'Novi Sad', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 45.2671, 'longitude' => 19.8335, 'created_at' => now()],
        ['city' => 'Niš', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 43.3209, 'longitude' => 21.8958, 'created_at' => now()],
        ['city' => 'Zagreb', 'country' => 'Hrvatska', 'country_code' => 'HR', 'latitude' => 45.8150, 'longitude' => 15.9819, 'created_at' => now()],
        ['city' => 'Berlin', 'country' => 'Nemačka', 'country_code' => 'DE', 'latitude' => 52.5200, 'longitude' => 13.4050, 'created_at' => now()],
        ['city' => 'Beč', 'country' => 'Austrija', 'country_code' => 'AT', 'latitude' => 48.2082, 'longitude' => 16.3738, 'created_at' => now()],
    ]);
});

describe('Location Search', function (): void {
    it('returns all locations without query', function (): void {
        $response = $this->getJson('/api/v1/locations/search');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'city', 'country', 'countryCode', 'latitude', 'longitude'],
                ],
                'meta' => ['currentPage', 'lastPage', 'perPage', 'total'],
            ]);

        expect($response->json('meta.total'))->toBe(6);
    });

    it('can search by city name', function (): void {
        $response = $this->getJson('/api/v1/locations/search?q=beograd');

        $response->assertOk();

        $data = $response->json('data');
        expect($data)->toHaveCount(1);
        expect($data[0]['city'])->toBe('Beograd');
    });

    it('can search by country name', function (): void {
        $response = $this->getJson('/api/v1/locations/search?q=srbija');

        $response->assertOk();

        $data = $response->json('data');
        expect($data)->toHaveCount(3);
    });

    it('can filter by country code', function (): void {
        $response = $this->getJson('/api/v1/locations/search?country_code=RS');

        $response->assertOk();

        $data = $response->json('data');
        expect($data)->toHaveCount(3);
        foreach ($data as $location) {
            expect($location['countryCode'])->toBe('RS');
        }
    });

    it('can combine query and country code filter', function (): void {
        $response = $this->getJson('/api/v1/locations/search?q=novi&country_code=RS');

        $response->assertOk();

        $data = $response->json('data');
        expect($data)->toHaveCount(1);
        expect($data[0]['city'])->toBe('Novi Sad');
    });

    it('returns case-insensitive results', function (): void {
        $response = $this->getJson('/api/v1/locations/search?q=BEOGRAD');

        $response->assertOk();

        $data = $response->json('data');
        expect($data)->toHaveCount(1);
        expect($data[0]['city'])->toBe('Beograd');
    });

    it('respects per_page parameter', function (): void {
        $response = $this->getJson('/api/v1/locations/search?per_page=2');

        $response->assertOk();

        expect($response->json('data'))->toHaveCount(2);
        expect($response->json('meta.perPage'))->toBe(2);
        expect($response->json('meta.total'))->toBe(6);
    });

    it('limits per_page to maximum 100', function (): void {
        $response = $this->getJson('/api/v1/locations/search?per_page=500');

        $response->assertOk();
        expect($response->json('meta.perPage'))->toBe(100);
    });

    it('returns empty results for non-matching query', function (): void {
        $response = $this->getJson('/api/v1/locations/search?q=nonexistentcity');

        $response->assertOk();
        expect($response->json('data'))->toBeEmpty();
        expect($response->json('meta.total'))->toBe(0);
    });

    it('returns location with coordinates', function (): void {
        $response = $this->getJson('/api/v1/locations/search?q=beograd');

        $response->assertOk();

        $location = $response->json('data.0');
        expect($location['latitude'])->toBe(44.8176);
        expect($location['longitude'])->toBe(20.4633);
    });
});
