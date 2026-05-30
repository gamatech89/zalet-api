<?php

namespace Tests\Feature;

use App\Models\Gift;
use App\Models\GiftCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminGiftTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user;
    protected GiftCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->user = User::factory()->create(['role' => 'user']);

        $this->category = GiftCategory::create([
            'name' => 'Balkan Special',
            'slug' => 'balkan-special',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    // ── Gift CRUD ──

    public function test_admin_can_list_all_gifts(): void
    {
        Gift::create(['name' => 'Active Gift', 'coin_price' => 10, 'icon_url' => '/test.png', 'is_active' => true]);
        Gift::create(['name' => 'Inactive Gift', 'coin_price' => 20, 'icon_url' => '/test2.png', 'is_active' => false]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/gifts');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_create_gift(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/gifts', [
            'name' => 'Test Gift',
            'coin_price' => 50,
            'category_id' => $this->category->id,
            'description' => 'A test gift',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Test Gift')
            ->assertJsonPath('data.coin_price', 50);

        $this->assertDatabaseHas('gift_catalog', ['name' => 'Test Gift']);
    }

    public function test_admin_can_create_gift_with_icon_upload(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->post('/api/v1/admin/gifts', [
            'name' => 'Icon Gift',
            'coin_price' => 25,
            'icon_2d' => UploadedFile::fake()->image('icon2d.png', 200, 200),
            'icon_3d' => UploadedFile::fake()->image('icon3d.png', 300, 300),
        ]);

        $response->assertStatus(201);

        $gift = Gift::where('name', 'Icon Gift')->first();
        $this->assertNotNull($gift->icon_2d);
        $this->assertNotNull($gift->icon_3d);
        Storage::disk('public')->assertExists($gift->icon_2d);
        Storage::disk('public')->assertExists($gift->icon_3d);
    }

    public function test_admin_can_update_gift(): void
    {
        $gift = Gift::create([
            'name' => 'Old Name',
            'coin_price' => 10,
            'icon_url' => '/test.png',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/gifts/{$gift->id}", [
            'name' => 'New Name',
            'coin_price' => 99,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.coin_price', 99);
    }

    public function test_admin_can_deactivate_gift(): void
    {
        $gift = Gift::create([
            'name' => 'To Deactivate',
            'coin_price' => 10,
            'icon_url' => '/test.png',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/gifts/{$gift->id}");

        $response->assertStatus(200);
        $this->assertFalse($gift->fresh()->is_active);
    }

    public function test_admin_can_upload_icon(): void
    {
        Storage::fake('public');

        $gift = Gift::create([
            'name' => 'Icon Upload Test',
            'coin_price' => 10,
            'icon_url' => '/test.png',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->post("/api/v1/admin/gifts/{$gift->id}/icon", [
            'icon' => UploadedFile::fake()->image('new-icon.png', 200, 200),
            'type' => '2d',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.field', 'icon_2d');

        $gift->refresh();
        $this->assertNotNull($gift->icon_2d);
        Storage::disk('public')->assertExists($gift->icon_2d);
    }

    public function test_admin_can_reorder_gifts(): void
    {
        $gift1 = Gift::create(['name' => 'Gift A', 'coin_price' => 10, 'icon_url' => '/a.png', 'sort_order' => 1]);
        $gift2 = Gift::create(['name' => 'Gift B', 'coin_price' => 20, 'icon_url' => '/b.png', 'sort_order' => 2]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/v1/admin/gifts/reorder', [
            'items' => [
                ['id' => $gift1->id, 'sort_order' => 2],
                ['id' => $gift2->id, 'sort_order' => 1],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals(2, $gift1->fresh()->sort_order);
        $this->assertEquals(1, $gift2->fresh()->sort_order);
    }

    // ── Category CRUD ──

    public function test_admin_can_list_categories(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/gift-categories');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_create_category(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/gift-categories', [
            'name' => 'Luxury',
            'sort_order' => 2,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Luxury')
            ->assertJsonPath('data.slug', 'luxury');
    }

    public function test_admin_can_update_category(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/gift-categories/{$this->category->id}", [
            'name' => 'Balkanski Specijal',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Balkanski Specijal');
    }

    public function test_admin_can_delete_category(): void
    {
        $gift = Gift::create([
            'name' => 'Cat Gift',
            'coin_price' => 10,
            'icon_url' => '/test.png',
            'category_id' => $this->category->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/gift-categories/{$this->category->id}");

        $response->assertStatus(200);
        $this->assertNull($gift->fresh()->category_id);
        $this->assertDatabaseMissing('gift_categories', ['id' => $this->category->id]);
    }

    // ── Authorization ──

    public function test_non_admin_cannot_access_gift_management(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/admin/gifts');

        $response->assertStatus(403);
    }

    public function test_non_admin_cannot_create_gift(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/admin/gifts', [
            'name' => 'Hack Gift',
            'coin_price' => 1,
        ]);

        $response->assertStatus(403);
    }

    // ── Validation ──

    public function test_create_gift_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/gifts', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'coin_price']);
    }

    public function test_create_gift_validates_price_minimum(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/gifts', [
            'name' => 'Zero Gift',
            'coin_price' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['coin_price']);
    }
}