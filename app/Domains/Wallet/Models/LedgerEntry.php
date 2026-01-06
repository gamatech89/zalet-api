<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Models;

use Database\Factories\LedgerEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable ledger entry for double-entry bookkeeping.
 *
 * @property int $id
 * @property int $wallet_id
 * @property string $type
 * @property int $amount
 * @property int $balance_after
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $description
 * @property array<string, mixed> $meta
 * @property \Carbon\Carbon $created_at
 * @property-read Wallet $wallet
 */
class LedgerEntry extends Model
{
    /** @use HasFactory<LedgerEntryFactory> */
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): LedgerEntryFactory
    {
        return LedgerEntryFactory::new();
    }

    public $timestamps = false;

    public const TYPE_DEPOSIT = 'deposit';
    public const TYPE_WITHDRAWAL = 'withdrawal';
    public const TYPE_GIFT_SENT = 'gift_sent';
    public const TYPE_GIFT_RECEIVED = 'gift_received';
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_REFUND = 'refund';
    public const TYPE_ADJUSTMENT = 'adjustment';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'wallet_id',
        'type',
        'amount',
        'balance_after',
        'reference_type',
        'reference_id',
        'description',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (LedgerEntry $entry): void {
            $entry->created_at = now();
        });

        // Prevent updates and deletes for immutability
        static::updating(function (): bool {
            throw new \RuntimeException('Ledger entries are immutable and cannot be updated');
        });

        static::deleting(function (): bool {
            throw new \RuntimeException('Ledger entries are immutable and cannot be deleted');
        });
    }

    /**
     * Get the wallet this entry belongs to.
     *
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Check if this is a credit entry.
     */
    public function isCredit(): bool
    {
        return $this->amount > 0;
    }

    /**
     * Check if this is a debit entry.
     */
    public function isDebit(): bool
    {
        return $this->amount < 0;
    }

    /**
     * Get absolute amount (always positive).
     */
    public function getAbsoluteAmount(): int
    {
        return abs($this->amount);
    }

    /**
     * Get all valid transaction types.
     *
     * @return array<string>
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_DEPOSIT,
            self::TYPE_WITHDRAWAL,
            self::TYPE_GIFT_SENT,
            self::TYPE_GIFT_RECEIVED,
            self::TYPE_PURCHASE,
            self::TYPE_REFUND,
            self::TYPE_ADJUSTMENT,
        ];
    }
}
