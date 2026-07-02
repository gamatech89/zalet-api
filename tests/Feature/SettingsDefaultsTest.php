<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\SettingsSeeder;
use Tests\TestCase;

class SettingsDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_fetch_default_prices(): void
    {
        $this->seed(SettingsSeeder::class);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/settings/defaults');

        $response->assertStatus(200)
            ->assertJsonPath('data.default_group_entry_price', 0)
            ->assertJsonPath('data.default_stream_entry_price', 0);
    }

    public function test_unauthenticated_request_is_denied(): void
    {
        $response = $this->getJson('/api/v1/settings/defaults');

        $response->assertStatus(401);
    }
}
