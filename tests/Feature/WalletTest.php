<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Services\CoinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected CoinService $coinService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->coinService = app(CoinService::class);
    }

    /**
     * Test unauthenticated access to wallet is denied.
     */
    public function test_unauthenticated_user_cannot_access_wallet(): void
    {
        $response = $this->getJson('/api/v1/wallet');

        $response->assertStatus(401);
    }

    /**
     * Test authenticated user can view their wallet.
     */
    public function test_authenticated_user_can_view_wallet(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/wallet');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'balance',
                    'currency',
                    'updated_at',
                ],
            ]);
    }

    /**
     * Test wallet is created automatically for new users.
     */
    public function test_wallet_is_created_automatically(): void
    {
        $this->assertDatabaseMissing('wallets', ['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/wallet');

        $response->assertStatus(200);
        $this->assertDatabaseHas('wallets', ['user_id' => $this->user->id]);
    }

    /**
     * Test wallet balance reflects deposits.
     */
    public function test_wallet_balance_reflects_deposits(): void
    {
        // Create wallet and add balance
        $wallet = $this->coinService->ensureWallet($this->user);
        $wallet->update(['balance' => 500.00]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/wallet');

        $response->assertStatus(200)
            ->assertJsonPath('data.balance', '500.00');
    }

    /**
     * Test user can view transaction history.
     */
    public function test_user_can_view_transaction_history(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/wallet/transactions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    /**
     * Test transaction history pagination works.
     */
    public function test_transaction_history_pagination(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/wallet/transactions?per_page=5');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 5);
    }

    /**
     * Test unauthenticated access to transactions is denied.
     */
    public function test_unauthenticated_user_cannot_access_transactions(): void
    {
        $response = $this->getJson('/api/v1/wallet/transactions');

        $response->assertStatus(401);
    }
}
