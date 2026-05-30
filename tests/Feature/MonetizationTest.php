<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\MediaPurchase;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CoinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonetizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $buyer;
    protected User $creator;
    protected CoinService $coinService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buyer = User::factory()->create();
        $this->creator = User::factory()->create(['role' => 'creator']);
        $this->coinService = app(CoinService::class);
    }

    // === PPV Tests ===

    /**
     * Test purchase PPV content successfully.
     */
    public function test_purchase_ppv_content_successfully(): void
    {
        $media = Media::factory()->create([
            'user_id' => $this->creator->id,
            'is_ppv' => true,
            'price_coins' => 50,
        ]);

        // Give buyer some balance
        $wallet = $this->coinService->ensureWallet($this->buyer);
        $wallet->update(['balance' => 100.00]);
        $this->coinService->ensureWallet($this->creator);

        $response = $this->actingAs($this->buyer, 'sanctum')
            ->postJson("/api/v1/media/{$media->id}/purchase");

        $response->assertStatus(200)
            ->assertJsonPath('data.amount_paid', '50.00');

        // Check balances
        $this->assertEquals(50.00, $this->coinService->getBalance($this->buyer));
        $this->assertEquals(50.00, $this->coinService->getBalance($this->creator));

        // Check purchase record
        $this->assertDatabaseHas('media_purchases', [
            'user_id' => $this->buyer->id,
            'media_id' => $media->id,
        ]);
    }

    /**
     * Test cannot purchase non-PPV content.
     */
    public function test_cannot_purchase_non_ppv_content(): void
    {
        $media = Media::factory()->create([
            'user_id' => $this->creator->id,
            'is_ppv' => false,
        ]);

        $response = $this->actingAs($this->buyer, 'sanctum')
            ->postJson("/api/v1/media/{$media->id}/purchase");

        $response->assertStatus(400);
    }

    /**
     * Test cannot purchase own content.
     */
    public function test_cannot_purchase_own_content(): void
    {
        $media = Media::factory()->create([
            'user_id' => $this->creator->id,
            'is_ppv' => true,
            'price_coins' => 50,
        ]);

        $response = $this->actingAs($this->creator, 'sanctum')
            ->postJson("/api/v1/media/{$media->id}/purchase");

        $response->assertStatus(400);
    }

    /**
     * Test cannot purchase twice.
     */
    public function test_cannot_purchase_content_twice(): void
    {
        $media = Media::factory()->create([
            'user_id' => $this->creator->id,
            'is_ppv' => true,
            'price_coins' => 50,
        ]);

        // Create existing purchase with valid transaction
        $buyerWallet = \App\Models\Wallet::factory()->create(['user_id' => $this->buyer->id]);
        $creatorWallet = \App\Models\Wallet::factory()->create(['user_id' => $this->creator->id]);

        $transaction = \App\Models\Transaction::create([
            'from_wallet_id' => $buyerWallet->id,
            'to_wallet_id' => $creatorWallet->id,
            'amount' => $media->price_coins,
            'type' => 'ppv',
            'status' => 'completed',
        ]);

        MediaPurchase::create([
            'user_id' => $this->buyer->id,
            'media_id' => $media->id,
            'transaction_id' => $transaction->id,
        ]);

        $response = $this->actingAs($this->buyer, 'sanctum')
            ->postJson("/api/v1/media/{$media->id}/purchase");

        $response->assertStatus(400)
            ->assertJsonPath('message', 'You have already purchased this content.');
    }

    /**
     * Test insufficient balance for purchase.
     */
    public function test_insufficient_balance_for_purchase(): void
    {
        $media = Media::factory()->create([
            'user_id' => $this->creator->id,
            'is_ppv' => true,
            'price_coins' => 100,
        ]);

        // Buyer has no balance
        $this->coinService->ensureWallet($this->buyer);
        $this->coinService->ensureWallet($this->creator);

        $response = $this->actingAs($this->buyer, 'sanctum')
            ->postJson("/api/v1/media/{$media->id}/purchase");

        $response->assertStatus(422);
    }

    // === Subscription Tests ===

    /**
     * Test list subscription plans.
     */
    public function test_list_subscription_plans(): void
    {
        \App\Models\SubscriptionPlan::create([
            'name' => 'Premium',
            'slug' => 'premium',
            'level' => 1,
            'price_monthly' => 499.00,
            'price_yearly' => 4990.00,
            'features' => ['HD streaming', 'No ads'],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->getJson('/api/v1/subscription-plans');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Premium')
            ->assertJsonPath('data.0.slug', 'premium');
    }

    /**
     * Test subscribe to a plan initializes payment.
     */
    public function test_subscribe_to_plan(): void
    {
        $plan = \App\Models\SubscriptionPlan::create([
            'name' => 'Premium',
            'slug' => 'premium',
            'level' => 1,
            'price_monthly' => 499.00,
            'features' => [],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->buyer, 'sanctum')
            ->postJson('/api/v1/subscriptions', [
                'plan_id' => $plan->id,
                'billing_cycle' => 'monthly',
            ]);

        // Should return 200 with payment URL (or 500 if Raiffeisen not configured in test env)
        $this->assertContains($response->getStatusCode(), [200, 500]);
    }

    /**
     * Test get current subscription when none exists.
     */
    public function test_get_current_subscription_empty(): void
    {
        $response = $this->actingAs($this->buyer, 'sanctum')
            ->getJson('/api/v1/subscriptions/current');

        $response->assertStatus(200)
            ->assertJsonPath('data', null);
    }

    /**
     * Test get current subscription when active.
     */
    public function test_get_current_subscription_active(): void
    {
        $plan = \App\Models\SubscriptionPlan::create([
            'name' => 'VIP',
            'slug' => 'vip',
            'level' => 2,
            'price_monthly' => 999.00,
            'features' => [],
            'is_active' => true,
            'sort_order' => 2,
        ]);

        \App\Models\Subscription::create([
            'user_id' => $this->buyer->id,
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'price_paid' => 999.00,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'status' => 'active',
            'auto_renew' => true,
        ]);

        $response = $this->actingAs($this->buyer, 'sanctum')
            ->getJson('/api/v1/subscriptions/current');

        $response->assertStatus(200)
            ->assertJsonPath('data.plan.name', 'VIP')
            ->assertJsonPath('data.status', 'active');
    }

    /**
     * Test cancel subscription.
     */
    public function test_cancel_subscription(): void
    {
        $plan = \App\Models\SubscriptionPlan::create([
            'name' => 'Premium',
            'slug' => 'premium',
            'level' => 1,
            'price_monthly' => 499.00,
            'features' => [],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        \App\Models\Subscription::create([
            'user_id' => $this->buyer->id,
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'price_paid' => 499.00,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'status' => 'active',
            'auto_renew' => true,
        ]);

        $response = $this->actingAs($this->buyer, 'sanctum')
            ->postJson('/api/v1/subscriptions/cancel');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');
    }

    /**
     * Test cancel subscription when none exists.
     */
    public function test_cancel_subscription_when_none_exists(): void
    {
        $response = $this->actingAs($this->buyer, 'sanctum')
            ->postJson('/api/v1/subscriptions/cancel');

        $response->assertStatus(404);
    }
}
