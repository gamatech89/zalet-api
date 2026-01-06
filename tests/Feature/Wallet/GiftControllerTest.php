<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use App\Domains\Wallet\Events\GiftSent;
use App\Domains\Wallet\Models\LedgerEntry;
use App\Domains\Wallet\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

describe('Gift Catalog Endpoint', function (): void {

    it('returns gift catalog for authenticated user', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/gifts');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'credits',
                        'icon',
                        'animation',
                    ],
                ],
            ]);

        $data = $response->json('data');
        expect(count($data))->toBe(5);

        // Verify specific gifts are present
        $giftIds = array_column($data, 'id');
        expect($giftIds)->toContain('rakija')
            ->and($giftIds)->toContain('rose')
            ->and($giftIds)->toContain('heart')
            ->and($giftIds)->toContain('crown')
            ->and($giftIds)->toContain('car');
    });

    it('requires authentication', function (): void {
        $response = $this->getJson('/api/v1/gifts');

        $response->assertUnauthorized();
    });

});

describe('Send Gift Endpoint', function (): void {

    it('sends gift successfully', function (): void {
        Event::fake([GiftSent::class]);

        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Wallet::factory()->forUser($sender)->withBalance(500)->create();
        Wallet::factory()->forUser($recipient)->withBalance(0)->create();

        $response = $this->actingAs($sender)
            ->postJson('/api/v1/gifts/send', [
                'recipient_id' => $recipient->id,
                'gift_type' => 'heart',
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'wallet' => ['balance'],
                    'transaction' => [
                        'id',
                        'transactionType',
                        'credits',
                        'gift' => ['type', 'name', 'icon', 'animation'],
                    ],
                    'gift' => ['type', 'name', 'credits', 'icon', 'animation'],
                ],
                'message',
            ]);

        expect($response->json('data.wallet.balance'))->toBe(475)
            ->and($response->json('data.transaction.credits'))->toBe(25)
            ->and($response->json('data.gift.type'))->toBe('heart')
            ->and($response->json('message'))->toBe('Gift sent successfully.');

        Event::assertDispatched(GiftSent::class);
    });

    it('sends gift with live session id', function (): void {
        Event::fake([GiftSent::class]);

        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Wallet::factory()->forUser($sender)->withBalance(500)->create();
        Wallet::factory()->forUser($recipient)->withBalance(0)->create();

        $response = $this->actingAs($sender)
            ->postJson('/api/v1/gifts/send', [
                'recipient_id' => $recipient->id,
                'gift_type' => 'rakija',
                'live_session_id' => 123,
            ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'transaction' => [
                        'liveSessionId' => 123,
                    ],
                ],
            ]);

        Event::assertDispatched(GiftSent::class, function (GiftSent $event): bool {
            return $event->liveSessionId === 123;
        });
    });

    it('fails with invalid gift type', function (): void {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Wallet::factory()->forUser($sender)->withBalance(500)->create();

        $response = $this->actingAs($sender)
            ->postJson('/api/v1/gifts/send', [
                'recipient_id' => $recipient->id,
                'gift_type' => 'invalid_gift',
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Invalid gift type: invalid_gift']);
    });

    it('fails when sending to yourself', function (): void {
        $user = User::factory()->create();
        Wallet::factory()->forUser($user)->withBalance(500)->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/gifts/send', [
                'recipient_id' => $user->id,
                'gift_type' => 'heart',
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Cannot send a gift to yourself']);
    });

    it('fails with insufficient balance', function (): void {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Wallet::factory()->forUser($sender)->withBalance(10)->create();
        Wallet::factory()->forUser($recipient)->withBalance(0)->create();

        $response = $this->actingAs($sender)
            ->postJson('/api/v1/gifts/send', [
                'recipient_id' => $recipient->id,
                'gift_type' => 'heart', // 25 credits needed
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Insufficient balance']);
    });

    it('validates required fields', function (): void {
        $sender = User::factory()->create();

        $response = $this->actingAs($sender)
            ->postJson('/api/v1/gifts/send', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recipient_id', 'gift_type']);
    });

    it('validates recipient exists', function (): void {
        $sender = User::factory()->create();

        $response = $this->actingAs($sender)
            ->postJson('/api/v1/gifts/send', [
                'recipient_id' => 99999,
                'gift_type' => 'heart',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recipient_id']);
    });

    it('requires authentication', function (): void {
        $response = $this->postJson('/api/v1/gifts/send', [
            'recipient_id' => 1,
            'gift_type' => 'heart',
        ]);

        $response->assertUnauthorized();
    });

});

describe('Creator Earnings Endpoint', function (): void {

    it('returns earnings summary', function (): void {
        Event::fake([GiftSent::class]);

        $creator = User::factory()->create();
        $fan = User::factory()->create();

        Wallet::factory()->forUser($creator)->withBalance(0)->create();
        Wallet::factory()->forUser($fan)->withBalance(500)->create();

        // Send some gifts
        $this->actingAs($fan)
            ->postJson('/api/v1/gifts/send', [
                'recipient_id' => $creator->id,
                'gift_type' => 'heart',
            ]);

        $this->actingAs($fan)
            ->postJson('/api/v1/gifts/send', [
                'recipient_id' => $creator->id,
                'gift_type' => 'rose',
            ]);

        $response = $this->actingAs($creator)
            ->getJson('/api/v1/earnings');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'totalCredits',
                    'totalGiftsReceived',
                    'periodStart',
                    'periodEnd',
                ],
            ]);

        expect($response->json('data.totalCredits'))->toBe(35) // 25 + 10
            ->and($response->json('data.totalGiftsReceived'))->toBe(2);
    });

    it('filters by date range', function (): void {
        $creator = User::factory()->create();
        Wallet::factory()->forUser($creator)->withBalance(0)->create();

        $startDate = now()->subDay()->toIso8601String();
        $endDate = now()->addDay()->toIso8601String();

        $response = $this->actingAs($creator)
            ->getJson('/api/v1/earnings?start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'periodStart' => $startDate,
                    'periodEnd' => $endDate,
                ],
            ]);
    });

    it('returns zero for user with no gifts', function (): void {
        $user = User::factory()->create();
        Wallet::factory()->forUser($user)->withBalance(100)->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/earnings');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'totalCredits' => 0,
                    'totalGiftsReceived' => 0,
                ],
            ]);
    });

    it('requires authentication', function (): void {
        $response = $this->getJson('/api/v1/earnings');

        $response->assertUnauthorized();
    });

});

describe('Earnings Breakdown Endpoint', function (): void {

    it('returns breakdown by gift type', function (): void {
        Event::fake([GiftSent::class]);

        $creator = User::factory()->create();
        $fan = User::factory()->create();

        Wallet::factory()->forUser($creator)->withBalance(0)->create();
        Wallet::factory()->forUser($fan)->withBalance(1000)->create();

        // Send multiple gifts
        $this->actingAs($fan)
            ->postJson('/api/v1/gifts/send', [
                'recipient_id' => $creator->id,
                'gift_type' => 'heart',
            ]);

        $this->actingAs($fan)
            ->postJson('/api/v1/gifts/send', [
                'recipient_id' => $creator->id,
                'gift_type' => 'heart',
            ]);

        $this->actingAs($fan)
            ->postJson('/api/v1/gifts/send', [
                'recipient_id' => $creator->id,
                'gift_type' => 'rose',
            ]);

        $response = $this->actingAs($creator)
            ->getJson('/api/v1/earnings/breakdown');

        $response->assertOk();

        $data = $response->json('data');
        expect($data)->toHaveKey('heart')
            ->and($data)->toHaveKey('rose')
            ->and($data['heart']['count'])->toBe(2)
            ->and($data['heart']['totalCredits'])->toBe(50)
            ->and($data['rose']['count'])->toBe(1)
            ->and($data['rose']['totalCredits'])->toBe(10);
    });

    it('returns empty breakdown for user with no gifts', function (): void {
        $user = User::factory()->create();
        Wallet::factory()->forUser($user)->withBalance(100)->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/earnings/breakdown');

        $response->assertOk()
            ->assertJson(['data' => []]);
    });

    it('requires authentication', function (): void {
        $response = $this->getJson('/api/v1/earnings/breakdown');

        $response->assertUnauthorized();
    });

});

describe('Gift Transaction in Wallet History', function (): void {

    it('shows gift transactions in sender wallet history', function (): void {
        Event::fake([GiftSent::class]);

        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Wallet::factory()->forUser($sender)->withBalance(500)->create();
        Wallet::factory()->forUser($recipient)->withBalance(0)->create();

        $this->actingAs($sender)
            ->postJson('/api/v1/gifts/send', [
                'recipient_id' => $recipient->id,
                'gift_type' => 'crown',
            ]);

        $response = $this->actingAs($sender)
            ->getJson('/api/v1/wallet/transactions');

        $response->assertOk();

        $transactions = $response->json('data');
        expect($transactions)->toHaveCount(1);

        $transaction = $transactions[0];
        expect($transaction['type'])->toBe(LedgerEntry::TYPE_GIFT_SENT)
            ->and($transaction['absoluteAmount'])->toBe(100)
            ->and($transaction['isDebit'])->toBeTrue();
    });

    it('shows gift transactions in recipient wallet history', function (): void {
        Event::fake([GiftSent::class]);

        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Wallet::factory()->forUser($sender)->withBalance(500)->create();
        Wallet::factory()->forUser($recipient)->withBalance(0)->create();

        $this->actingAs($sender)
            ->postJson('/api/v1/gifts/send', [
                'recipient_id' => $recipient->id,
                'gift_type' => 'rose',
            ]);

        $response = $this->actingAs($recipient)
            ->getJson('/api/v1/wallet/transactions');

        $response->assertOk();

        $transactions = $response->json('data');
        expect($transactions)->toHaveCount(1);

        $transaction = $transactions[0];
        expect($transaction['type'])->toBe(LedgerEntry::TYPE_GIFT_RECEIVED)
            ->and($transaction['absoluteAmount'])->toBe(10)
            ->and($transaction['isCredit'])->toBeTrue();
    });

});
