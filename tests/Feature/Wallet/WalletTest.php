<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use App\Domains\Wallet\Models\LedgerEntry;
use App\Domains\Wallet\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Wallet Model', function (): void {

    it('can create a wallet', function (): void {
        $wallet = Wallet::factory()->create();

        expect($wallet)
            ->toBeInstanceOf(Wallet::class)
            ->and($wallet->balance)->toBeInt()
            ->and($wallet->currency)->toBe('CREDITS');
    });

    it('belongs to a user', function (): void {
        $wallet = Wallet::factory()->create();

        expect($wallet->user)->toBeInstanceOf(User::class);
    });

    it('can create wallet with zero balance', function (): void {
        $wallet = Wallet::factory()->empty()->create();

        expect($wallet->balance)->toBe(0);
    });

    it('can create wallet with specific balance', function (): void {
        $wallet = Wallet::factory()->withBalance(500)->create();

        expect($wallet->balance)->toBe(500);
    });

    it('credits wallet correctly', function (): void {
        $wallet = Wallet::factory()->withBalance(100)->create();

        $entry = $wallet->credit(
            amount: 50,
            type: LedgerEntry::TYPE_DEPOSIT,
            description: 'Test deposit'
        );

        $wallet->refresh();

        expect($wallet->balance)->toBe(150)
            ->and($entry->amount)->toBe(50)
            ->and($entry->balance_after)->toBe(150)
            ->and($entry->isCredit())->toBeTrue();
    });

    it('debits wallet correctly', function (): void {
        $wallet = Wallet::factory()->withBalance(100)->create();

        $entry = $wallet->debit(
            amount: 30,
            type: LedgerEntry::TYPE_GIFT_SENT,
            description: 'Test gift'
        );

        $wallet->refresh();

        expect($wallet->balance)->toBe(70)
            ->and($entry->amount)->toBe(-30)
            ->and($entry->balance_after)->toBe(70)
            ->and($entry->isDebit())->toBeTrue();
    });

    it('throws exception when debiting more than balance', function (): void {
        $wallet = Wallet::factory()->withBalance(50)->create();

        expect(fn () => $wallet->debit(100, LedgerEntry::TYPE_GIFT_SENT))
            ->toThrow(\RuntimeException::class, 'Insufficient balance');
    });

    it('throws exception when crediting negative amount', function (): void {
        $wallet = Wallet::factory()->create();

        expect(fn () => $wallet->credit(-50, LedgerEntry::TYPE_DEPOSIT))
            ->toThrow(\InvalidArgumentException::class, 'Credit amount must be positive');
    });

    it('throws exception when debiting negative amount', function (): void {
        $wallet = Wallet::factory()->withBalance(100)->create();

        expect(fn () => $wallet->debit(-50, LedgerEntry::TYPE_GIFT_SENT))
            ->toThrow(\InvalidArgumentException::class, 'Debit amount must be positive');
    });

    it('can check if debit is possible', function (): void {
        $wallet = Wallet::factory()->withBalance(100)->create();

        expect($wallet->canDebit(50))->toBeTrue()
            ->and($wallet->canDebit(100))->toBeTrue()
            ->and($wallet->canDebit(101))->toBeFalse();
    });

    it('formats balance correctly', function (): void {
        $wallet = Wallet::factory()->withBalance(10000)->create();

        expect($wallet->getFormattedBalance())->toBe('10,000 CREDITS');
    });

    it('has ledger entries relationship', function (): void {
        $wallet = Wallet::factory()->withBalance(0)->create();
        $wallet->credit(100, LedgerEntry::TYPE_DEPOSIT);
        $wallet->credit(50, LedgerEntry::TYPE_GIFT_RECEIVED);

        $wallet->load('ledgerEntries');

        expect($wallet->ledgerEntries)->toHaveCount(2);
    });

});

describe('LedgerEntry Model', function (): void {

    it('belongs to a wallet', function (): void {
        $wallet = Wallet::factory()->withBalance(0)->create();
        $entry = $wallet->credit(100, LedgerEntry::TYPE_DEPOSIT);

        expect($entry->wallet)->toBeInstanceOf(Wallet::class);
    });

    it('identifies credit entries', function (): void {
        $wallet = Wallet::factory()->withBalance(0)->create();
        $entry = $wallet->credit(100, LedgerEntry::TYPE_DEPOSIT);

        expect($entry->isCredit())->toBeTrue()
            ->and($entry->isDebit())->toBeFalse();
    });

    it('identifies debit entries', function (): void {
        $wallet = Wallet::factory()->withBalance(100)->create();
        $entry = $wallet->debit(50, LedgerEntry::TYPE_GIFT_SENT);

        expect($entry->isDebit())->toBeTrue()
            ->and($entry->isCredit())->toBeFalse();
    });

    it('returns absolute amount', function (): void {
        $wallet = Wallet::factory()->withBalance(100)->create();
        $entry = $wallet->debit(50, LedgerEntry::TYPE_GIFT_SENT);

        expect($entry->amount)->toBe(-50)
            ->and($entry->getAbsoluteAmount())->toBe(50);
    });

    it('returns valid types', function (): void {
        $types = LedgerEntry::getTypes();

        expect($types)
            ->toContain(LedgerEntry::TYPE_DEPOSIT)
            ->toContain(LedgerEntry::TYPE_WITHDRAWAL)
            ->toContain(LedgerEntry::TYPE_GIFT_SENT)
            ->toContain(LedgerEntry::TYPE_GIFT_RECEIVED)
            ->toContain(LedgerEntry::TYPE_PURCHASE)
            ->toContain(LedgerEntry::TYPE_REFUND)
            ->toContain(LedgerEntry::TYPE_ADJUSTMENT);
    });

    it('is immutable - cannot update', function (): void {
        $wallet = Wallet::factory()->withBalance(0)->create();
        $entry = $wallet->credit(100, LedgerEntry::TYPE_DEPOSIT);

        expect(fn () => $entry->update(['amount' => 999]))
            ->toThrow(\RuntimeException::class, 'Ledger entries are immutable');
    });

    it('is immutable - cannot delete', function (): void {
        $wallet = Wallet::factory()->withBalance(0)->create();
        $entry = $wallet->credit(100, LedgerEntry::TYPE_DEPOSIT);

        expect(fn () => $entry->delete())
            ->toThrow(\RuntimeException::class, 'Ledger entries are immutable');
    });

});
