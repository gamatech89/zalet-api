<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use App\Domains\Wallet\Contracts\PaymentProviderInterface;
use App\Domains\Wallet\Models\PaymentIntent;
use App\Domains\Wallet\Models\Wallet;
use App\Domains\Wallet\Services\StubRaiAcceptService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Use stub service with success behavior
    $this->stubService = new StubRaiAcceptService('success');
    $this->app->instance(PaymentProviderInterface::class, $this->stubService);
});

describe('GET /api/v1/wallet/packages', function (): void {

    it('returns available credit packages', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/wallet/packages');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'credits',
                        'priceCents',
                        'formattedPrice',
                        'currency',
                    ],
                ],
            ]);

        // Check package contents
        $data = $response->json('data');
        expect($data)->toHaveCount(3);

        $starter = collect($data)->firstWhere('id', 'starter');
        expect($starter['credits'])->toBe(100)
            ->and($starter['priceCents'])->toBe(500);
    });

});

describe('POST /api/v1/wallet/purchase', function (): void {

    it('initiates credit purchase', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/wallet/purchase', [
                'package_id' => 'starter',
                'language' => 'sr',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'intent' => [
                        'id',
                        'provider',
                        'amountCents',
                        'creditsAmount',
                        'status',
                    ],
                    'paymentUrl',
                ],
                'message',
            ]);

        // Verify payment intent was created
        expect(PaymentIntent::count())->toBe(1);

        $intent = PaymentIntent::first();
        expect($intent->user_id)->toBe($user->id)
            ->and($intent->amount_cents)->toBe(500)
            ->and($intent->credits_amount)->toBe(100);
    });

    it('validates package_id is required', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/wallet/purchase', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['package_id']);
    });

    it('validates package_id must be valid', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/wallet/purchase', [
                'package_id' => 'invalid',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['package_id']);
    });

    it('validates language must be valid', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/wallet/purchase', [
                'package_id' => 'starter',
                'language' => 'fr',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['language']);
    });

    it('requires authentication', function (): void {
        $response = $this->postJson('/api/v1/wallet/purchase', [
            'package_id' => 'starter',
        ]);

        $response->assertUnauthorized();
    });

});

describe('GET /api/v1/wallet/purchase/{uuid}/status', function (): void {

    it('returns payment intent status', function (): void {
        $user = User::factory()->create();
        $intent = PaymentIntent::factory()->forUser($user)->completed()->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/wallet/purchase/{$intent->uuid}/status");

        $response->assertOk()
            ->assertJsonPath('data.id', $intent->uuid)
            ->assertJsonPath('data.status', PaymentIntent::STATUS_COMPLETED)
            ->assertJsonPath('data.isCompleted', true);
    });

    it('returns 404 for non-existent intent', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/wallet/purchase/non-existent-uuid/status');

        $response->assertNotFound();
    });

    it('returns 404 for other users intent', function (): void {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $intent = PaymentIntent::factory()->forUser($otherUser)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/wallet/purchase/{$intent->uuid}/status");

        $response->assertNotFound();
    });

    it('requires authentication', function (): void {
        $user = User::factory()->create();
        $intent = PaymentIntent::factory()->forUser($user)->create();

        $response = $this->getJson("/api/v1/wallet/purchase/{$intent->uuid}/status");

        $response->assertUnauthorized();
    });

});

describe('GET /api/v1/wallet/purchase/history', function (): void {

    it('returns user purchase history', function (): void {
        $user = User::factory()->create();
        PaymentIntent::factory()->forUser($user)->count(3)->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/wallet/purchase/history');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('only returns own intents', function (): void {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        PaymentIntent::factory()->forUser($user)->count(2)->create();
        PaymentIntent::factory()->forUser($otherUser)->count(3)->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/wallet/purchase/history');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('filters by status', function (): void {
        $user = User::factory()->create();
        PaymentIntent::factory()->forUser($user)->completed()->count(2)->create();
        PaymentIntent::factory()->forUser($user)->pending()->create();
        PaymentIntent::factory()->forUser($user)->failed()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/wallet/purchase/history?status=completed');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('returns empty for new user', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/wallet/purchase/history');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('requires authentication', function (): void {
        $response = $this->getJson('/api/v1/wallet/purchase/history');

        $response->assertUnauthorized();
    });

});

describe('Full purchase flow', function (): void {

    it('completes full purchase journey', function (): void {
        $user = User::factory()->create();

        // Step 1: Get packages
        $packagesResponse = $this->actingAs($user)
            ->getJson('/api/v1/wallet/packages');

        $packagesResponse->assertOk();
        $packages = $packagesResponse->json('data');
        $starterPackage = collect($packages)->firstWhere('id', 'starter');

        expect($starterPackage)->not->toBeNull();

        // Step 2: Initiate purchase
        $purchaseResponse = $this->actingAs($user)
            ->postJson('/api/v1/wallet/purchase', [
                'package_id' => 'starter',
                'language' => 'sr',
            ]);

        $purchaseResponse->assertStatus(201);
        $intentUuid = $purchaseResponse->json('data.intent.id');
        $paymentUrl = $purchaseResponse->json('data.paymentUrl');

        expect($paymentUrl)->not->toBeNull();

        // Step 3: Check pending status
        $statusResponse = $this->actingAs($user)
            ->getJson("/api/v1/wallet/purchase/{$intentUuid}/status");

        $statusResponse->assertOk()
            ->assertJsonPath('data.status', PaymentIntent::STATUS_PROCESSING);

        // Step 4: Simulate webhook (success)
        $intent = PaymentIntent::where('uuid', $intentUuid)->first();

        $webhookResponse = $this->postJson('/api/v1/webhooks/raiaccept', [
            'orderIdentification' => $intent->provider_order_id,
            'transactionId' => 'tx_test_success',
            'status' => 'success',
            'responseCode' => '00',
            'amountCents' => $intent->amount_cents,
        ]);

        $webhookResponse->assertOk();

        // Step 5: Verify completed status
        $finalStatusResponse = $this->actingAs($user)
            ->getJson("/api/v1/wallet/purchase/{$intentUuid}/status");

        $finalStatusResponse->assertOk()
            ->assertJsonPath('data.status', PaymentIntent::STATUS_COMPLETED)
            ->assertJsonPath('data.isCompleted', true);

        // Step 6: Verify credits in wallet
        $walletResponse = $this->actingAs($user)
            ->getJson('/api/v1/wallet');

        $walletResponse->assertOk()
            ->assertJsonPath('data.balance', 100);
    });

    it('handles failed payment in flow', function (): void {
        $user = User::factory()->create();

        // Initiate purchase
        $purchaseResponse = $this->actingAs($user)
            ->postJson('/api/v1/wallet/purchase', [
                'package_id' => 'popular',
            ]);

        $purchaseResponse->assertStatus(201);
        $intentUuid = $purchaseResponse->json('data.intent.id');

        // Simulate failed webhook
        $intent = PaymentIntent::where('uuid', $intentUuid)->first();

        $this->postJson('/api/v1/webhooks/raiaccept', [
            'orderIdentification' => $intent->provider_order_id,
            'transactionId' => 'tx_test_fail',
            'status' => 'failed',
            'responseCode' => '51',
            'amountCents' => $intent->amount_cents,
        ]);

        // Verify failed status
        $statusResponse = $this->actingAs($user)
            ->getJson("/api/v1/wallet/purchase/{$intentUuid}/status");

        $statusResponse->assertOk()
            ->assertJsonPath('data.status', PaymentIntent::STATUS_FAILED)
            ->assertJsonPath('data.isFailed', true);

        // Verify no credits added
        $wallet = Wallet::where('user_id', $user->id)->first();
        expect($wallet)->toBeNull();
    });

});
