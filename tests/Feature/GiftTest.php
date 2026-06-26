<?php

namespace Tests\Feature;

use App\Models\Gift;
use App\Models\User;
use App\Services\CoinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GiftTest extends TestCase
{
    use RefreshDatabase;

    protected User $sender;
    protected User $recipient;
    protected CoinService $coinService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sender = User::factory()->create();
        $this->recipient = User::factory()->create();
        $this->coinService = app(CoinService::class);

        // Create test gifts
        Gift::create([
            'name' => 'Heart',
            'coin_price' => 10,
            'icon_url' => '/images/gifts/heart.png',
            'is_active' => true,
        ]);

        Gift::create([
            'name' => 'Inactive Gift',
            'coin_price' => 50,
            'icon_url' => '/images/gifts/inactive.png',
            'is_active' => false,
        ]);
    }

    /**
     * Test gift catalog is publicly accessible.
     */
    public function test_gift_catalog_is_publicly_accessible(): void
    {
        $response = $this->getJson('/api/v1/gifts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'coin_price', 'icon_url'],
                ],
            ]);
    }

    /**
     * Test only active gifts are shown in catalog.
     */
    public function test_only_active_gifts_shown_in_catalog(): void
    {
        $response = $this->getJson('/api/v1/gifts');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Heart');
    }

    /**
     * Test sending a gift successfully.
     */
    public function test_send_gift_successfully(): void
    {
        $gift = Gift::where('name', 'Heart')->first();
        
        // Give sender some balance
        $wallet = $this->coinService->ensureWallet($this->sender);
        $wallet->update(['balance' => 100.00]);

        $response = $this->actingAs($this->sender, 'sanctum')
            ->postJson('/api/v1/gifts/send', [
                'recipient_id' => $this->recipient->id,
                'gift_id' => $gift->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'transaction_id',
                    'gift' => ['id', 'name', 'icon_url'],
                    'recipient' => ['id', 'username'],
                    'amount',
                    'new_balance',
                ],
            ])
            ->assertJsonPath('data.gift.name', 'Heart')
            ->assertJsonPath('data.amount', 10);

        // Verify balances — platform keeps 50% of gifts (gift_creator_percent = 50)
        $this->assertEquals(90.00, $this->coinService->getBalance($this->sender));
        $this->assertEquals(5.00, $this->coinService->getBalance($this->recipient));
    }

    /**
     * Test sending gift with insufficient balance fails.
     */
    public function test_send_gift_fails_with_insufficient_balance(): void
    {
        $gift = Gift::where('name', 'Heart')->first();
        
        // Give sender small balance
        $wallet = $this->coinService->ensureWallet($this->sender);
        $wallet->update(['balance' => 5.00]);

        $response = $this->actingAs($this->sender, 'sanctum')
            ->postJson('/api/v1/gifts/send', [
                'recipient_id' => $this->recipient->id,
                'gift_id' => $gift->id,
            ]);

        $response->assertStatus(422);
    }

    /**
     * Test cannot send inactive gift.
     */
    public function test_cannot_send_inactive_gift(): void
    {
        $gift = Gift::where('name', 'Inactive Gift')->first();
        
        $wallet = $this->coinService->ensureWallet($this->sender);
        $wallet->update(['balance' => 100.00]);

        $response = $this->actingAs($this->sender, 'sanctum')
            ->postJson('/api/v1/gifts/send', [
                'recipient_id' => $this->recipient->id,
                'gift_id' => $gift->id,
            ]);

        $response->assertStatus(404);
    }

    /**
     * Test cannot send gift to yourself.
     */
    public function test_cannot_send_gift_to_yourself(): void
    {
        $gift = Gift::where('name', 'Heart')->first();

        $response = $this->actingAs($this->sender, 'sanctum')
            ->postJson('/api/v1/gifts/send', [
                'recipient_id' => $this->sender->id,
                'gift_id' => $gift->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recipient_id']);
    }

    /**
     * Test sending gift requires authentication.
     */
    public function test_send_gift_requires_authentication(): void
    {
        $gift = Gift::where('name', 'Heart')->first();

        $response = $this->postJson('/api/v1/gifts/send', [
            'recipient_id' => $this->recipient->id,
            'gift_id' => $gift->id,
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test gift transaction has correct metadata.
     */
    public function test_gift_transaction_has_correct_metadata(): void
    {
        $gift = Gift::where('name', 'Heart')->first();
        
        $wallet = $this->coinService->ensureWallet($this->sender);
        $wallet->update(['balance' => 100.00]);
        $this->coinService->ensureWallet($this->recipient);

        $response = $this->actingAs($this->sender, 'sanctum')
            ->postJson('/api/v1/gifts/send', [
                'recipient_id' => $this->recipient->id,
                'gift_id' => $gift->id,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('transactions', [
            'type' => 'tip',
            'gift_id' => $gift->id,
            'amount' => '10.00',
            'status' => 'completed',
        ]);
    }
}
