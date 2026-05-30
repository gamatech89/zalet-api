<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardCategory extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'slug',
        'name_en',
        'name_sr',
        'icon',
        'sort_order',
        'is_active',
        'board_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // === Scopes ===

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('board_id');
    }

    /**
     * Get categories for a specific board (global + board-specific).
     */
    public function scopeForBoard($query, ?string $boardId)
    {
        return $query->where(function ($q) use ($boardId) {
            $q->whereNull('board_id');
            if ($boardId) {
                $q->orWhere('board_id', $boardId);
            }
        });
    }

    // === Relationships ===

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    // === Helpers ===

    /**
     * Get localized name.
     */
    public function translatedName(string $locale = 'sr'): string
    {
        $field = "name_{$locale}";
        return $this->{$field} ?? $this->name_en;
    }
}
