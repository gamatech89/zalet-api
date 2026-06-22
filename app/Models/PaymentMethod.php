<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'card_brand',
        'last_four',
        'expiry_month',
        'expiry_year',
        'gateway_token',
        'is_default',
        'label',
    ];

    protected function casts(): array
    {
        return [
            'gateway_token' => 'encrypted',
            'is_default' => 'boolean',
        ];
    }

    // ── Relationships ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ──

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // ── Helpers ──

    /**
     * Mark this payment method as the default, unsetting any other defaults.
     */
    public function makeDefault(): void
    {
        // Unset all other defaults for this user
        static::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Get a display label for the card.
     */
    public function displayName(): string
    {
        $brand = ucfirst($this->card_brand);
        return $this->label ?? "{$brand} ending in {$this->last_four}";
    }

    /**
     * Check if the card is expired.
     */
    public function isExpired(): bool
    {
        // Unknown expiry (Raiffeisen didn't send UPCTokenExp) — treat as valid
        if ($this->expiry_month === '00' || $this->expiry_year === '00') {
            return false;
        }

        $expiry = \Carbon\Carbon::createFromFormat(
            'y-m',
            "{$this->expiry_year}-{$this->expiry_month}"
        )->endOfMonth();

        return $expiry->isPast();
    }
}
