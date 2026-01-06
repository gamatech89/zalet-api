<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use App\Domains\Wallet\Actions\GetTransactionHistoryAction;
use App\Domains\Wallet\Actions\GetWalletAction;
use App\Domains\Wallet\Actions\TransferCreditsAction;
use App\Domains\Wallet\Models\LedgerEntry;
use App\Domains\Wallet\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('GetWalletAction', function (): void {

    it('creates wallet if not exists', function (): void {
        $user = User::factory()->create();
        $action = app(GetWalletAction::class);

        $wallet = $action->execute($user);

        expect($wallet)->toBeInstanceOf(Wallet::class)
            ->and($wallet->user_id)->toBe($user->id)
            ->and($wallet->balance)->toBe(0);
    });

    it('returns existing wallet', function (): void {
        $user = User::factory()->create();
        $existingWallet = Wallet::factory()->forUser($user)->withBalance(500)->create();

        $action = app(GetWalletAction::class);
        $wallet = $action->execute($user);

        expect($wallet->id)->toBe($existingWallet->id)
            ->and($wallet->balance)->toBe(500);
    });

    it('loads recent transactions when requested', function (): void {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->withBalance(0)->create();

        // Create some transactions
        $wallet->credit(100, LedgerEntry::TYPE_DEPOSIT);
        $wallet->credit(50, LedgerEntry::TYPE_GIFT_RECEIVED);
        $wallet->debit(30, LedgerEntry::TYPE_GIFT_SENT);

        $action = app(GetWalletAction::class);
        $result = $action->execute($user, withRecentTransactions: true, recentLimit: 2);

        expect($result->relationLoaded('ledgerEntries'))->toBeTrue()
            ->and($result->ledgerEntries)->toHaveCount(2);
    });

    it('does not load transactions by default', function (): void {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->withBalance(0)->create();
        $wallet->credit(100, LedgerEntry::TYPE_DEPOSIT);

        $action = app(GetWalletAction::class);
        $result = $action->execute($user, withRecentTransactions: false);

        expect($result->relationLoaded('ledgerEntries'))->toBeFalse();
    });

});

describe('GetTransactionHistoryAction', function (): void {

    it('returns paginated transactions', function (): void {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->withBalance(0)->create();

        for ($i = 0; $i < 15; $i++) {
            $wallet->credit(10, LedgerEntry::TYPE_DEPOSIT);
        }

        $action = app(GetTransactionHistoryAction::class);
        $result = $action->execute($user, perPage: 5);

        expect($result->total())->toBe(15)
            ->and($result->perPage())->toBe(5)
            ->and($result->count())->toBe(5);
    });

    it('filters by transaction type', function (): void {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->withBalance(0)->create();

        $wallet->credit(100, LedgerEntry::TYPE_DEPOSIT);
        $wallet->credit(50, LedgerEntry::TYPE_GIFT_RECEIVED);
        $wallet->debit(30, LedgerEntry::TYPE_GIFT_SENT);

        $action = app(GetTransactionHistoryAction::class);
        $deposits = $action->execute($user, type: LedgerEntry::TYPE_DEPOSIT);

        expect($deposits->total())->toBe(1)
            ->and($deposits->first()->type)->toBe(LedgerEntry::TYPE_DEPOSIT);
    });

    it('returns empty paginator when no wallet exists', function (): void {
        $user = User::factory()->create();

        $action = app(GetTransactionHistoryAction::class);
        $result = $action->execute($user);

        expect($result->total())->toBe(0);
    });

    it('orders by created_at descending', function (): void {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->withBalance(0)->create();

        $wallet->credit(100, LedgerEntry::TYPE_DEPOSIT, description: 'First');
        sleep(1); // Ensure different timestamps
        $wallet->credit(200, LedgerEntry::TYPE_DEPOSIT, description: 'Second');

        $action = app(GetTransactionHistoryAction::class);
        $result = $action->execute($user);

        expect($result->first()->description)->toBe('Second');
    });

});

describe('TransferCreditsAction', function (): void {

    it('transfers credits between users', function (): void {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Wallet::factory()->forUser($sender)->withBalance(500)->create();
        Wallet::factory()->forUser($recipient)->withBalance(100)->create();

        $action = app(TransferCreditsAction::class);
        $result = $action->execute($sender, $recipient, 200, 'Test transfer');

        $sender->refresh();
        $recipient->refresh();

        expect($sender->wallet->balance)->toBe(300)
            ->and($recipient->wallet->balance)->toBe(300)
            ->and($result['sender_entry']->type)->toBe(LedgerEntry::TYPE_GIFT_SENT)
            ->and($result['recipient_entry']->type)->toBe(LedgerEntry::TYPE_GIFT_RECEIVED);
    });

    it('creates wallets if they do not exist', function (): void {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        // Manually create sender wallet with balance (no factory to simulate fresh user)
        Wallet::create(['user_id' => $sender->id, 'balance' => 100]);

        $action = app(TransferCreditsAction::class);
        $action->execute($sender, $recipient, 50);

        $recipient->refresh();

        expect($recipient->wallet)->not->toBeNull()
            ->and($recipient->wallet->balance)->toBe(50);
    });

    it('throws exception for insufficient balance', function (): void {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Wallet::factory()->forUser($sender)->withBalance(50)->create();

        $action = app(TransferCreditsAction::class);

        expect(fn () => $action->execute($sender, $recipient, 100))
            ->toThrow(\RuntimeException::class, 'Insufficient balance');
    });

    it('throws exception for negative amount', function (): void {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $action = app(TransferCreditsAction::class);

        expect(fn () => $action->execute($sender, $recipient, -50))
            ->toThrow(\InvalidArgumentException::class, 'Transfer amount must be positive');
    });

    it('throws exception for self transfer', function (): void {
        $user = User::factory()->create();

        $action = app(TransferCreditsAction::class);

        expect(fn () => $action->execute($user, $user, 100))
            ->toThrow(\InvalidArgumentException::class, 'Cannot transfer to yourself');
    });

    it('creates correct ledger entries', function (): void {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Wallet::factory()->forUser($sender)->withBalance(200)->create();

        $action = app(TransferCreditsAction::class);
        $result = $action->execute($sender, $recipient, 100, 'Gift for you');

        expect($result['sender_entry']->amount)->toBe(-100)
            ->and($result['sender_entry']->reference_type)->toBe(User::class)
            ->and($result['sender_entry']->reference_id)->toBe($recipient->id)
            ->and($result['recipient_entry']->amount)->toBe(100)
            ->and($result['recipient_entry']->reference_type)->toBe(User::class)
            ->and($result['recipient_entry']->reference_id)->toBe($sender->id);
    });

});
