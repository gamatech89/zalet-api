<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\PlaceTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed a test place (Vienna)
        $place = Place::create([
            'external_id' => '12345',
            'country_code' => 'AT',
            'coordinates' => ['lat' => 48.2082, 'lng' => 16.3738],
        ]);

        PlaceTranslation::create(['place_id' => $place->id, 'locale' => 'en', 'name' => 'Vienna']);
        PlaceTranslation::create(['place_id' => $place->id, 'locale' => 'de', 'name' => 'Wien']);
        PlaceTranslation::create(['place_id' => $place->id, 'locale' => 'sr', 'name' => 'Beč']);
    }

    public function test_can_search_location_by_localized_name(): void
    {
        // Search 'Beč' (Serbian) — must be URL-encoded so test client passes bytes correctly
        $response = $this->getJson('/api/v1/locations/search?q=' . urlencode('Beč'));

        $response->assertStatus(200)
            ->assertJsonPath('data.0.country_code', 'AT')
            ->assertJsonPath('data.0.translations.sr', 'Beč');

        // Search 'Wien' (German)
        $response = $this->getJson('/api/v1/locations/search?q=Wien');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.country_code', 'AT');
    }

    public function test_can_search_with_locale_filter(): void
    {
        $response = $this->getJson('/api/v1/locations/search?q=Vienna&locale=en');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
            
        // Should not find if searching 'Vienna' with locale 'de' (assuming strict match on name+locale if implemented that way, 
        // but our implementation searches ALL names, the locale filter is optional for prioritization or stricter filtering. 
        // Let's check our Place::search implementation: it applies whereHas on translations.
        // So searching 'Vienna' with locale 'de' should actually return nothing if 'Vienna' is only stored as 'en'.
        
        $responseMismatch = $this->getJson('/api/v1/locations/search?q=Vienna&locale=de');
        $responseMismatch->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_can_get_place_details(): void
    {
        $place = Place::first();

        $response = $this->getJson("/api/v1/locations/places/{$place->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $place->id)
            ->assertJsonPath('data.translations.en', 'Vienna');
    }

    public function test_user_can_update_profile_with_place_id(): void
    {
        $user = User::factory()->create();
        $place = Place::first();

        $response = $this->actingAs($user)->putJson('/api/v1/profile', [
            'hometown_place_id' => $place->id,
            'current_place_id' => $place->id,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'hometown_place_id' => $place->id,
            'current_place_id' => $place->id,
        ]);

        // Test computed attribute
        $profile = $user->fresh()->profile;
        // Since test app locale is likely 'en', it should use English translation or fallback
        // Our 'getNameAttribute' tries app locale, then 'en'.
        $this->assertStringContainsString('Vienna', $profile->hometown); 
    }
}
