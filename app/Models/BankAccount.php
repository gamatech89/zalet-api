<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'bank_name',
        'account_number',
        'last_four',
        'is_default',
        'label',
    ];

    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
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
     * Mark this bank account as the default, unsetting any other defaults.
     */
    public function makeDefault(): void
    {
        static::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Get a display label for the bank account.
     */
    public function displayName(): string
    {
        return $this->label ?? "{$this->bank_name} ····{$this->last_four}";
    }
}
