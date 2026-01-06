<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Models;

use App\Domains\Identity\Models\User;
use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Wallet Aggregate Root - Encapsulates balance invariants.
 *
 * @property int $id
 * @property int $user_id
 * @property int $balance
 * @property string $currency
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, LedgerEntry> $ledgerEntries
 */
class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): WalletFactory
    {
        return WalletFactory::new();
    }

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'balance',
        'currency',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance' => 'integer',
        ];
    }

    /**
     * Get the user that owns the wallet.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all ledger entries for this wallet.
     *
     * @return HasMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class)->orderByDesc('created_at');
    }

    /**
     * Credit the wallet (add funds).
     *
     * @param int $amount Positive amount to credit
     * @param string $type Transaction type
     * @param string|null $referenceType Polymorphic reference type
     * @param int|null $referenceId Polymorphic reference ID
     * @param string|null $description Optional description
     * @param array<string, mixed> $meta Additional metadata
     * @return LedgerEntry
     */
    public function credit(
        int $amount,
        string $type,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null,
        array $meta = []
    ): LedgerEntry {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive');
        }

        return DB::transaction(function () use ($amount, $type, $referenceType, $referenceId, $description, $meta) {
            // Lock the wallet row for update
            $this->lockForUpdate()->first();

            $newBalance = $this->balance + $amount;

            $this->update(['balance' => $newBalance]);

            return $this->ledgerEntries()->create([
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'meta' => $meta,
            ]);
        });
    }

    /**
     * Debit the wallet (remove funds).
     *
     * @param int $amount Positive amount to debit
     * @param string $type Transaction type
     * @param string|null $referenceType Polymorphic reference type
     * @param int|null $referenceId Polymorphic reference ID
     * @param string|null $description Optional description
     * @param array<string, mixed> $meta Additional metadata
     * @return LedgerEntry
     * @throws \RuntimeException If insufficient balance
     */
    public function debit(
        int $amount,
        string $type,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null,
        array $meta = []
    ): LedgerEntry {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Debit amount must be positive');
        }

        return DB::transaction(function () use ($amount, $type, $referenceType, $referenceId, $description, $meta) {
            // Lock the wallet row for update
            /** @var Wallet $wallet */
            $wallet = self::query()->lockForUpdate()->find($this->id);

            if (!$wallet->canDebit($amount)) {
                throw new \RuntimeException('Insufficient balance');
            }

            $newBalance = $wallet->balance - $amount;

            $wallet->update(['balance' => $newBalance]);
            $this->balance = $newBalance;

            return $this->ledgerEntries()->create([
                'type' => $type,
                'amount' => -$amount, // Negative for debits
                'balance_after' => $newBalance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'meta' => $meta,
            ]);
        });
    }

    /**
     * Check if wallet can debit the given amount.
     */
    public function canDebit(int $amount): bool
    {
        return $this->balance >= $amount;
    }

    /**
     * Get balance in display format.
     */
    public function getFormattedBalance(): string
    {
        return number_format($this->balance) . ' ' . $this->currency;
    }
}
