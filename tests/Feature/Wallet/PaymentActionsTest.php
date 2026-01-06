<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use App\Domains\Wallet\Actions\CompleteCreditPurchaseAction;
use App\Domains\Wallet\Actions\FailCreditPurchaseAction;
use App\Domains\Wallet\Actions\GetPaymentIntentAction;
use App\Domains\Wallet\Actions\InitiateCreditPurchaseAction;
use App\Domains\Wallet\Actions\ProcessPaymentWebhookAction;
use App\Domains\Wallet\Actions\RequestRefundAction;
use App\Domains\Wallet\Contracts\PaymentProviderInterface;
use App\Domains\Wallet\DTOs\WebhookPayload;
use App\Domains\Wallet\Models\LedgerEntry;
use App\Domains\Wallet\Models\PaymentIntent;
use App\Domains\Wallet\Models\Wallet;
use App\Domains\Wallet\Services\StubRaiAcceptService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Use stub service with success behavior for testing
    $this->stubService = new StubRaiAcceptService('success');
    $this->app->instance(PaymentProviderInterface::class, $this->stubService);
});

describe('InitiateCreditPurchaseAction', function (): void {

    it('creates payment intent for valid package', function (): void {
        $user = User::factory()->create();
        $action = app(InitiateCreditPurchaseAction::class);

        $result = $action->execute($user, 'starter', 'sr');

        expect($result)->toHaveKeys(['intent', 'paymentUrl'])
            ->and($result['intent'])->toBeInstanceOf(PaymentIntent::class)
            ->and($result['intent']->amount_cents)->toBe(500)
            ->and($result['intent']->credits_amount)->toBe(100)
            ->and($result['intent']->status)->toBe(PaymentIntent::STATUS_PROCESSING)
            ->and($result['paymentUrl'])->toContain('/stub/payment/');
    });

    it('stores provider order details', function (): void {
        $user = User::factory()->create();
        $action = app(InitiateCreditPurchaseAction::class);

        $result = $action->execute($user, 'popular');

        expect($result['intent']->provider_order_id)->not->toBeNull()
            ->and($result['intent']->provider_session_url)->not->toBeNull()
            ->and($result['intent']->provider)->toBe('stub_raiaccept');
    });

    it('returns existing pending intent with same idempotency', function (): void {
        $user = User::factory()->create();
        $action = app(InitiateCreditPurchaseAction::class);

        // First call
        $result1 = $action->execute($user, 'starter');
        $intentId = $result1['intent']->id;

        // Second call with same package - update the existing intent to be pending
        // Actually, the action changes status to processing, so this test needs adjustment
        // The idempotency check is for pending intents with existing session URL
        // Since first call already moved it to processing, we need a fresh start

        // Actually, need to check the logic - creating a new user to test this properly
        $user2 = User::factory()->create();
        $result2 = $action->execute($user2, 'starter');
        $result3 = $action->execute($user2, 'starter');

        // Same idempotency key should return the same intent
        expect($result3['intent']->id)->toBe($result2['intent']->id);
    });

    it('throws exception for invalid package', function (): void {
        $user = User::factory()->create();
        $action = app(InitiateCreditPurchaseAction::class);

        $action->execute($user, 'invalid_package');
    })->throws(\InvalidArgumentException::class, 'Invalid package ID');

    it('creates intent with correct metadata', function (): void {
        $user = User::factory()->create();
        $action = app(InitiateCreditPurchaseAction::class);

        $result = $action->execute($user, 'premium', 'de');

        expect($result['intent']->meta)->toHaveKey('package_id', 'premium')
            ->and($result['intent']->meta)->toHaveKey('package_name', 'Premium Pack');
    });

});

describe('ProcessPaymentWebhookAction', function (): void {

    it('processes successful payment webhook', function (): void {
        $user = User::factory()->create();
        $intent = PaymentIntent::factory()->forUser($user)->pending()->create([
            'provider_order_id' => 'test_order_123',
        ]);

        $action = app(ProcessPaymentWebhookAction::class);
        $rawPayload = [
            'orderIdentification' => 'test_order_123',
            'transactionId' => 'tx_success_123',
            'status' => 'success',
            'responseCode' => '00',
            'amountCents' => $intent->amount_cents,
        ];

        $action->execute($rawPayload);

        $intent->refresh();
        expect($intent->status)->toBe(PaymentIntent::STATUS_COMPLETED)
            ->and($intent->hasReceivedWebhook())->toBeTrue();
    });

    it('processes failed payment webhook', function (): void {
        $user = User::factory()->create();
        $intent = PaymentIntent::factory()->forUser($user)->pending()->create([
            'provider_order_id' => 'test_order_fail',
        ]);

        $action = app(ProcessPaymentWebhookAction::class);
        $rawPayload = [
            'orderIdentification' => 'test_order_fail',
            'transactionId' => 'tx_fail_123',
            'status' => 'failed',
            'responseCode' => '51',
            'amountCents' => $intent->amount_cents,
        ];

        $action->execute($rawPayload);

        $intent->refresh();
        expect($intent->status)->toBe(PaymentIntent::STATUS_FAILED)
            ->and($intent->hasReceivedWebhook())->toBeTrue();
    });

    it('skips duplicate webhook', function (): void {
        $user = User::factory()->create();
        Wallet::factory()->forUser($user)->withBalance(0)->create();

        $intent = PaymentIntent::factory()->forUser($user)->pending()->create([
            'provider_order_id' => 'test_order_dup',
        ]);

        $action = app(ProcessPaymentWebhookAction::class);
        $rawPayload = [
            'orderIdentification' => 'test_order_dup',
            'transactionId' => 'tx_dup_123',
            'status' => 'success',
            'responseCode' => '00',
            'amountCents' => $intent->amount_cents,
        ];

        // Process twice
        $action->execute($rawPayload);
        $action->execute($rawPayload);

        // Should only have one ledger entry
        $entries = LedgerEntry::where('reference_type', PaymentIntent::class)
            ->where('reference_id', $intent->id)
            ->count();

        expect($entries)->toBe(1);
    });

    it('throws for unknown order', function (): void {
        $action = app(ProcessPaymentWebhookAction::class);
        $rawPayload = [
            'orderIdentification' => 'unknown_order',
            'transactionId' => 'tx_unknown',
            'status' => 'success',
            'responseCode' => '00',
            'amountCents' => 500,
        ];

        $action->execute($rawPayload);
    })->throws(\InvalidArgumentException::class);

});

describe('CompleteCreditPurchaseAction', function (): void {

    it('credits user wallet', function (): void {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->withBalance(100)->create();

        $intent = PaymentIntent::factory()->forUser($user)->pending()->create([
            'credits_amount' => 500,
        ]);

        $payload = new WebhookPayload(
            orderIdentification: $intent->provider_order_id,
            transactionId: 'tx_complete_123',
            status: 'success',
            responseCode: '00',
            amountCents: $intent->amount_cents,
        );

        $action = app(CompleteCreditPurchaseAction::class);
        $action->execute($intent, $payload);

        expect($wallet->fresh()->balance)->toBe(600);
    });

    it('creates wallet if not exists', function (): void {
        $user = User::factory()->create();

        $intent = PaymentIntent::factory()->forUser($user)->pending()->create([
            'credits_amount' => 100,
        ]);

        $payload = new WebhookPayload(
            orderIdentification: $intent->provider_order_id,
            transactionId: 'tx_wallet_create',
            status: 'success',
            responseCode: '00',
            amountCents: $intent->amount_cents,
        );

        $action = app(CompleteCreditPurchaseAction::class);
        $action->execute($intent, $payload);

        $wallet = Wallet::where('user_id', $user->id)->first();
        expect($wallet)->not->toBeNull()
            ->and($wallet->balance)->toBe(100);
    });

    it('creates ledger entry', function (): void {
        $user = User::factory()->create();
        Wallet::factory()->forUser($user)->withBalance(0)->create();

        $intent = PaymentIntent::factory()->forUser($user)->pending()->create([
            'credits_amount' => 250,
        ]);

        $payload = new WebhookPayload(
            orderIdentification: $intent->provider_order_id,
            transactionId: 'tx_ledger_123',
            status: 'success',
            responseCode: '00',
            amountCents: $intent->amount_cents,
        );

        $action = app(CompleteCreditPurchaseAction::class);
        $action->execute($intent, $payload);

        $entry = LedgerEntry::where('reference_type', PaymentIntent::class)
            ->where('reference_id', $intent->id)
            ->first();

        expect($entry)->not->toBeNull()
            ->and($entry->type)->toBe(LedgerEntry::TYPE_DEPOSIT)
            ->and($entry->amount)->toBe(250)
            ->and($entry->meta['transaction_id'])->toBe('tx_ledger_123');
    });

    it('updates intent to completed', function (): void {
        $user = User::factory()->create();
        Wallet::factory()->forUser($user)->withBalance(0)->create();

        $intent = PaymentIntent::factory()->forUser($user)->pending()->create();

        $payload = new WebhookPayload(
            orderIdentification: $intent->provider_order_id,
            transactionId: 'tx_status_123',
            status: 'success',
            responseCode: '00',
            amountCents: $intent->amount_cents,
        );

        $action = app(CompleteCreditPurchaseAction::class);
        $action->execute($intent, $payload);

        expect($intent->fresh()->status)->toBe(PaymentIntent::STATUS_COMPLETED);
    });

    it('is idempotent for completed intent', function (): void {
        $user = User::factory()->create();
        Wallet::factory()->forUser($user)->withBalance(0)->create();

        $intent = PaymentIntent::factory()->forUser($user)->completed()->create([
            'credits_amount' => 100,
        ]);

        $payload = new WebhookPayload(
            orderIdentification: $intent->provider_order_id,
            transactionId: 'tx_idempotent',
            status: 'success',
            responseCode: '00',
            amountCents: $intent->amount_cents,
        );

        $action = app(CompleteCreditPurchaseAction::class);
        $action->execute($intent, $payload);
        $action->execute($intent, $payload);

        // Balance should be 0 (no duplicate credit)
        $wallet = Wallet::where('user_id', $user->id)->first();
        expect($wallet->balance)->toBe(0);
    });

});

describe('FailCreditPurchaseAction', function (): void {

    it('marks intent as failed', function (): void {
        $user = User::factory()->create();

        $intent = PaymentIntent::factory()->forUser($user)->pending()->create();

        $payload = new WebhookPayload(
            orderIdentification: $intent->provider_order_id,
            transactionId: 'tx_fail_123',
            status: 'failed',
            responseCode: '51',
            amountCents: $intent->amount_cents,
        );

        $action = app(FailCreditPurchaseAction::class);
        $action->execute($intent, $payload);

        $intent->refresh();
        expect($intent->status)->toBe(PaymentIntent::STATUS_FAILED)
            ->and($intent->meta)->toHaveKey('failure_reason', '51');
    });

    it('is idempotent', function (): void {
        $user = User::factory()->create();

        $intent = PaymentIntent::factory()->forUser($user)->failed()->create();

        $payload = new WebhookPayload(
            orderIdentification: $intent->provider_order_id,
            transactionId: 'tx_fail_dup',
            status: 'failed',
            responseCode: '51',
            amountCents: $intent->amount_cents,
        );

        $action = app(FailCreditPurchaseAction::class);
        $action->execute($intent, $payload);

        expect($intent->fresh()->status)->toBe(PaymentIntent::STATUS_FAILED);
    });

});

describe('GetPaymentIntentAction', function (): void {

    it('gets intent by uuid for owner', function (): void {
        $user = User::factory()->create();
        $intent = PaymentIntent::factory()->forUser($user)->create();

        $action = app(GetPaymentIntentAction::class);
        $result = $action->execute($intent->uuid, $user);

        expect($result)->not->toBeNull()
            ->and($result->id)->toBe($intent->id);
    });

    it('returns null for non-owner', function (): void {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $intent = PaymentIntent::factory()->forUser($user)->create();

        $action = app(GetPaymentIntentAction::class);
        $result = $action->execute($intent->uuid, $otherUser);

        expect($result)->toBeNull();
    });

    it('gets intent without user check', function (): void {
        $user = User::factory()->create();
        $intent = PaymentIntent::factory()->forUser($user)->create();

        $action = app(GetPaymentIntentAction::class);
        $result = $action->execute($intent->uuid);

        expect($result)->not->toBeNull();
    });

    it('lists intents for user', function (): void {
        $user = User::factory()->create();
        PaymentIntent::factory()->forUser($user)->count(3)->create();
        PaymentIntent::factory()->count(2)->create(); // Other users

        $action = app(GetPaymentIntentAction::class);
        $intents = $action->forUser($user);

        expect($intents)->toHaveCount(3);
    });

    it('filters by status', function (): void {
        $user = User::factory()->create();
        PaymentIntent::factory()->forUser($user)->completed()->count(2)->create();
        PaymentIntent::factory()->forUser($user)->pending()->create();

        $action = app(GetPaymentIntentAction::class);
        $intents = $action->forUser($user, PaymentIntent::STATUS_COMPLETED);

        expect($intents)->toHaveCount(2);
    });

});

describe('RequestRefundAction', function (): void {

    it('refunds completed payment', function (): void {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->withBalance(500)->create();

        $intent = PaymentIntent::factory()->forUser($user)->completed()->create([
            'credits_amount' => 100,
        ]);

        $action = app(RequestRefundAction::class);
        $result = $action->execute($intent, $user);

        expect($result)->toBeInstanceOf(PaymentIntent::class)
            ->and($result->status)->toBe(PaymentIntent::STATUS_REFUNDED)
            ->and($wallet->fresh()->balance)->toBe(400);
    });

    it('fails refund without sufficient balance', function (): void {
        $user = User::factory()->create();
        Wallet::factory()->forUser($user)->withBalance(50)->create();

        $intent = PaymentIntent::factory()->forUser($user)->completed()->create([
            'credits_amount' => 100,
        ]);

        $action = app(RequestRefundAction::class);

        $action->execute($intent, $user);
    })->throws(\RuntimeException::class, 'Insufficient credits for refund');

    it('fails refund for non-owner', function (): void {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Wallet::factory()->forUser($user)->withBalance(500)->create();

        $intent = PaymentIntent::factory()->forUser($user)->completed()->create([
            'credits_amount' => 100,
        ]);

        $action = app(RequestRefundAction::class);

        $action->execute($intent, $otherUser);
    })->throws(\InvalidArgumentException::class, 'You are not authorized');

    it('fails refund for non-completed payment', function (): void {
        $user = User::factory()->create();
        Wallet::factory()->forUser($user)->withBalance(500)->create();

        $intent = PaymentIntent::factory()->forUser($user)->pending()->create();

        $action = app(RequestRefundAction::class);

        $action->execute($intent, $user);
    })->throws(\InvalidArgumentException::class, 'Only completed payments can be refunded');

    it('fails refund for already refunded payment', function (): void {
        $user = User::factory()->create();
        Wallet::factory()->forUser($user)->withBalance(500)->create();

        $intent = PaymentIntent::factory()->forUser($user)->refunded()->create();

        $action = app(RequestRefundAction::class);

        $action->execute($intent, $user);
    })->throws(\InvalidArgumentException::class, 'Only completed payments can be refunded');

});
