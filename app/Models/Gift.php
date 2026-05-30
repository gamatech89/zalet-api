<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gift extends Model
{
    use HasFactory;

    protected $table = 'gift_catalog';

    protected $fillable = [
        'name',
        'coin_price',
        'icon_url',
        'icon_2d',
        'icon_3d',
        'is_active',
        'is_epic',
        'is_rare',
        'category_id',
        'level',
        'sort_order',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'coin_price' => 'integer',
            'is_active' => 'boolean',
            'is_epic' => 'boolean',
            'is_rare' => 'boolean',
            'level' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    // ── Relationships ──

    public function category(): BelongsTo
    {
        return $this->belongsTo(GiftCategory::class , 'category_id');
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('coin_price');
    }

    public function scopeCategory($query, $categorySlug)
    {
        return $query->whereHas('category', function ($q) use ($categorySlug) {
            $q->where('slug', $categorySlug);
        });
    }

    // ── Accessors ──

    /**
     * Get the best available display icon.
     * Priority: icon_2d → icon_url → null
     */
    public function getDisplayIconAttribute(): ?string
    {
        return $this->icon_2d ?? $this->icon_url ?? null;
    }
}