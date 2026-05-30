<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Services\CoinService;
use App\Services\RaiffeisenPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
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
     * Test deposit endpoint returns payment URL.
     */
    public function test_deposit_returns_payment_url(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/wallet/deposit', [
                'amount' => 1000, // 1000 RSD
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'payment_url',
                    'order_id',
                    'amount',
                    'currency',
                ],
            ])
            ->assertJsonPath('data.amount', 1000)
            ->assertJsonPath('data.currency', 'RSD');

        // Verify pending transaction was created
        $this->assertDatabaseHas('transactions', [
            'type' => 'deposit',
            'status' => 'pending',
        ]);
    }

    /**
     * Test deposit requires minimum amount.
     */
    public function test_deposit_requires_minimum_amount(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/wallet/deposit', [
                'amount' => 50, // Below minimum (100)
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /**
     * Test deposit has maximum limit.
     */
    public function test_deposit_has_maximum_limit(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/wallet/deposit', [
                'amount' => 1000000, // Over max (500,000)
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /**
     * Test deposit requires authentication.
     */
    public function test_deposit_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/wallet/deposit', [
            'amount' => 1000,
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test withdrawal request is successful.
     */
    public function test_withdrawal_request_is_successful(): void
    {
        // Give user some balance
        $wallet = $this->coinService->ensureWallet($this->user);
        $wallet->update(['balance' => 5000.00]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/wallet/withdraw', [
                'amount' => 1000,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'transaction_id',
                    'amount',
                    'status',
                    'new_balance',
                ],
            ])
            ->assertJsonPath('data.status', 'pending');

        // Verify new_balance (could be int or float)
        $this->assertEquals(4000, $response->json('data.new_balance'));

        // Verify withdrawal transaction was created
        $this->assertDatabaseHas('transactions', [
            'type' => 'withdrawal',
            'status' => 'pending',
            'amount' => '1000.00',
        ]);
    }

    /**
     * Test withdrawal fails with insufficient balance.
     */
    public function test_withdrawal_fails_with_insufficient_balance(): void
    {
        $wallet = $this->coinService->ensureWallet($this->user);
        $wallet->update(['balance' => 100.00]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/wallet/withdraw', [
                'amount' => 500,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Insufficient balance for withdrawal.');
    }

    /**
     * Test withdrawal requires minimum amount.
     */
    public function test_withdrawal_requires_minimum_amount(): void
    {
        $wallet = $this->coinService->ensureWallet($this->user);
        $wallet->update(['balance' => 5000.00]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/wallet/withdraw', [
                'amount' => 100, // Below minimum (500)
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /**
     * Test withdrawal requires authentication.
     */
    public function test_withdrawal_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/wallet/withdraw', [
            'amount' => 1000,
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test webhook endpoint exists.
     */
    public function test_webhook_endpoint_exists(): void
    {
        // Note: We don't test full webhook processing here as it requires
        // valid signatures. This just verifies the endpoint is accessible.
        $response = $this->postJson('/api/v1/webhooks/raiffeisen', [
            'MerchantID' => '1731553',
            'TerminalID' => 'E1731563',
            'OrderID' => 'TEST-123',
        ]);

        // Should return 200 even without valid signature (will be 'reverse' action)
        $response->assertStatus(200);
    }
}
