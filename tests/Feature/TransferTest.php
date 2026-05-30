<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Services\CoinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferTest extends TestCase
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
    }

    /**
     * Test successful transfer between users.
     */
    public function test_successful_transfer_between_users(): void
    {
        // Give sender some balance
        $wallet = $this->coinService->ensureWallet($this->sender);
        $wallet->update(['balance' => 1000.00]);

        $response = $this->actingAs($this->sender, 'sanctum')
            ->postJson('/api/v1/wallet/transfer', [
                'recipient_id' => $this->recipient->id,
                'amount' => 100,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'transaction_id',
                    'amount',
                    'recipient' => ['id', 'username'],
                    'new_balance',
                ],
            ])
            ->assertJsonPath('data.amount', '100.00');

        // Verify new_balance (could be int or float)
        $this->assertEquals(900, $response->json('data.new_balance'));

        // Verify balances
        $this->assertEquals(900.00, $this->coinService->getBalance($this->sender));
        $this->assertEquals(100.00, $this->coinService->getBalance($this->recipient));
    }

    /**
     * Test transfer fails with insufficient balance.
     */
    public function test_transfer_fails_with_insufficient_balance(): void
    {
        // Give sender small balance
        $wallet = $this->coinService->ensureWallet($this->sender);
        $wallet->update(['balance' => 50.00]);

        $response = $this->actingAs($this->sender, 'sanctum')
            ->postJson('/api/v1/wallet/transfer', [
                'recipient_id' => $this->recipient->id,
                'amount' => 100,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Insufficient balance.');
    }

    /**
     * Test user cannot transfer to themselves.
     */
    public function test_user_cannot_transfer_to_themselves(): void
    {
        $wallet = $this->coinService->ensureWallet($this->sender);
        $wallet->update(['balance' => 1000.00]);

        $response = $this->actingAs($this->sender, 'sanctum')
            ->postJson('/api/v1/wallet/transfer', [
                'recipient_id' => $this->sender->id,
                'amount' => 100,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recipient_id']);
    }

    /**
     * Test transfer requires valid recipient.
     */
    public function test_transfer_requires_valid_recipient(): void
    {
        $response = $this->actingAs($this->sender, 'sanctum')
            ->postJson('/api/v1/wallet/transfer', [
                'recipient_id' => '00000000-0000-0000-0000-000000000000',
                'amount' => 100,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recipient_id']);
    }

    /**
     * Test transfer requires positive amount.
     */
    public function test_transfer_requires_positive_amount(): void
    {
        $response = $this->actingAs($this->sender, 'sanctum')
            ->postJson('/api/v1/wallet/transfer', [
                'recipient_id' => $this->recipient->id,
                'amount' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /**
     * Test transfer has maximum limit.
     */
    public function test_transfer_has_maximum_limit(): void
    {
        $response = $this->actingAs($this->sender, 'sanctum')
            ->postJson('/api/v1/wallet/transfer', [
                'recipient_id' => $this->recipient->id,
                'amount' => 500000, // Over max
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /**
     * Test transfer creates transaction record.
     */
    public function test_transfer_creates_transaction_record(): void
    {
        $wallet = $this->coinService->ensureWallet($this->sender);
        $wallet->update(['balance' => 1000.00]);
        $this->coinService->ensureWallet($this->recipient);

        $response = $this->actingAs($this->sender, 'sanctum')
            ->postJson('/api/v1/wallet/transfer', [
                'recipient_id' => $this->recipient->id,
                'amount' => 50,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('transactions', [
            'type' => 'tip',
            'status' => 'completed',
            'amount' => '50.00',
        ]);
    }

    /**
     * Test unauthenticated user cannot transfer.
     */
    public function test_unauthenticated_user_cannot_transfer(): void
    {
        $response = $this->postJson('/api/v1/wallet/transfer', [
            'recipient_id' => $this->recipient->id,
            'amount' => 100,
        ]);

        $response->assertStatus(401);
    }
}
