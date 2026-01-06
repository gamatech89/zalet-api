<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\HasUuid;
use Database\Factories\PaymentIntentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payment intent for tracking external payment gateway transactions.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $provider
 * @property string|null $provider_order_id
 * @property string|null $provider_session_url
 * @property int $amount_cents
 * @property int $credits_amount
 * @property string $currency
 * @property string $status
 * @property string $idempotency_key
 * @property \Carbon\Carbon|null $webhook_received_at
 * @property array<string, mixed> $meta
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read User $user
 */
class PaymentIntent extends Model
{
    /** @use HasFactory<PaymentIntentFactory> */
    use HasFactory;
    use HasUuid;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): PaymentIntentFactory
    {
        return PaymentIntentFactory::new();
    }

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'provider',
        'provider_order_id',
        'provider_session_url',
        'amount_cents',
        'credits_amount',
        'currency',
        'status',
        'idempotency_key',
        'webhook_received_at',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'credits_amount' => 'integer',
            'webhook_received_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * Get the user that owns this payment intent.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if payment is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if payment is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if payment has failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if webhook has already been processed.
     */
    public function hasReceivedWebhook(): bool
    {
        return $this->webhook_received_at !== null;
    }

    /**
     * Mark as webhook received (for idempotency).
     */
    public function markWebhookReceived(): void
    {
        $this->update(['webhook_received_at' => now()]);
    }

    /**
     * Get amount in display format (EUR).
     */
    public function getFormattedAmount(): string
    {
        return number_format($this->amount_cents / 100, 2) . ' ' . $this->currency;
    }

    /**
     * Get all valid statuses.
     *
     * @return array<string>
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_REFUNDED,
            self::STATUS_CANCELLED,
        ];
    }
}
