<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use App\Domains\Wallet\Models\LedgerEntry;
use App\Domains\Wallet\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('GET /api/v1/wallet', function (): void {

    it('returns wallet for authenticated user', function (): void {
        $user = User::factory()->create();
        Wallet::factory()->forUser($user)->withBalance(500)->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/wallet');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'balance',
                    'formattedBalance',
                    'currency',
                    'recentTransactions',
                    'createdAt',
                    'updatedAt',
                ],
            ])
            ->assertJson([
                'data' => [
                    'balance' => 500,
                    'currency' => 'CREDITS',
                ],
            ]);
    });

    it('creates wallet if not exists', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/wallet');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'balance' => 0,
                ],
            ]);

        $this->assertDatabaseHas('wallets', ['user_id' => $user->id]);
    });

    it('includes recent transactions', function (): void {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->withBalance(0)->create();

        $wallet->credit(100, LedgerEntry::TYPE_DEPOSIT);
        $wallet->credit(50, LedgerEntry::TYPE_GIFT_RECEIVED);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/wallet?include_recent=1');

        $response->assertOk()
            ->assertJsonCount(2, 'data.recentTransactions');
    });

    it('requires authentication', function (): void {
        $response = $this->getJson('/api/v1/wallet');

        $response->assertUnauthorized();
    });

});

describe('GET /api/v1/wallet/transactions', function (): void {

    it('returns paginated transactions', function (): void {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->withBalance(0)->create();

        for ($i = 0; $i < 5; $i++) {
            $wallet->credit(10, LedgerEntry::TYPE_DEPOSIT);
        }

        $response = $this->actingAs($user)
            ->getJson('/api/v1/wallet/transactions');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'amount',
                        'absoluteAmount',
                        'balanceAfter',
                        'isCredit',
                        'isDebit',
                        'createdAt',
                    ],
                ],
                'meta' => [
                    'currentPage',
                    'lastPage',
                    'perPage',
                    'total',
                ],
            ]);
    });

    it('filters by transaction type', function (): void {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->withBalance(0)->create();

        $wallet->credit(100, LedgerEntry::TYPE_DEPOSIT);
        $wallet->credit(50, LedgerEntry::TYPE_GIFT_RECEIVED);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/wallet/transactions?type=deposit');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJson([
                'data' => [
                    ['type' => 'deposit'],
                ],
            ]);
    });

    it('respects per_page parameter', function (): void {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->withBalance(0)->create();

        for ($i = 0; $i < 10; $i++) {
            $wallet->credit(10, LedgerEntry::TYPE_DEPOSIT);
        }

        $response = $this->actingAs($user)
            ->getJson('/api/v1/wallet/transactions?per_page=3');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJson([
                'meta' => [
                    'perPage' => 3,
                    'total' => 10,
                ],
            ]);
    });

    it('requires authentication', function (): void {
        $response = $this->getJson('/api/v1/wallet/transactions');

        $response->assertUnauthorized();
    });

});

describe('POST /api/v1/wallet/transfer', function (): void {

    it('transfers credits to another user', function (): void {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Wallet::factory()->forUser($sender)->withBalance(500)->create();

        $response = $this->actingAs($sender)
            ->postJson('/api/v1/wallet/transfer', [
                'recipient_uuid' => $recipient->uuid,
                'amount' => 100,
                'description' => 'Test gift',
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'wallet',
                    'transaction',
                ],
                'message',
            ])
            ->assertJson([
                'data' => [
                    'wallet' => [
                        'balance' => 400,
                    ],
                ],
                'message' => 'Credits transferred successfully.',
            ]);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $sender->id,
            'balance' => 400,
        ]);

        $recipient->refresh();
        expect($recipient->wallet->balance)->toBe(100);
    });

    it('validates required fields', function (): void {
        $user = User::factory()->create();
        Wallet::factory()->forUser($user)->withBalance(100)->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/wallet/transfer', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient_uuid', 'amount']);
    });

    it('validates minimum amount', function (): void {
        $user = User::factory()->create();
        $recipient = User::factory()->create();
        Wallet::factory()->forUser($user)->withBalance(100)->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/wallet/transfer', [
                'recipient_uuid' => $recipient->uuid,
                'amount' => 0,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    });

    it('validates recipient exists', function (): void {
        $user = User::factory()->create();
        Wallet::factory()->forUser($user)->withBalance(100)->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/wallet/transfer', [
                'recipient_uuid' => '00000000-0000-0000-0000-000000000000',
                'amount' => 50,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient_uuid']);
    });

    it('returns error for insufficient balance', function (): void {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Wallet::factory()->forUser($sender)->withBalance(50)->create();

        $response = $this->actingAs($sender)
            ->postJson('/api/v1/wallet/transfer', [
                'recipient_uuid' => $recipient->uuid,
                'amount' => 100,
            ]);

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'Insufficient balance',
            ]);
    });

    it('returns error for self transfer', function (): void {
        $user = User::factory()->create();
        Wallet::factory()->forUser($user)->withBalance(100)->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/wallet/transfer', [
                'recipient_uuid' => $user->uuid,
                'amount' => 50,
            ]);

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'Cannot transfer to yourself',
            ]);
    });

    it('requires authentication', function (): void {
        $response = $this->postJson('/api/v1/wallet/transfer', [
            'recipient_uuid' => '00000000-0000-0000-0000-000000000000',
            'amount' => 100,
        ]);

        $response->assertUnauthorized();
    });

});

describe('GET /api/v1/wallet/transaction-types', function (): void {

    it('returns available transaction types', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/wallet/transaction-types');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
            ]);

        $types = $response->json('data');
        expect($types)
            ->toContain('deposit')
            ->toContain('withdrawal')
            ->toContain('gift_sent')
            ->toContain('gift_received');
    });

    it('requires authentication', function (): void {
        $response = $this->getJson('/api/v1/wallet/transaction-types');

        $response->assertUnauthorized();
    });

});
