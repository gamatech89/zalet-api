<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class Place extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'external_id',
        'google_place_id',
        'source',
        'type',
        'country_code',
        'region',
        'coordinates',
    ];

    protected $casts = [
        'coordinates' => 'array',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(PlaceTranslation::class);
    }

    /**
     * Get the translation for a specific locale, or fallback to English/first.
     */
    public function translation(string $locale = 'en'): HasOne
    {
        return $this->hasOne(PlaceTranslation::class)->where('locale', $locale);
    }

    /**
     * Scope to search for a place by name in any language.
     * Uses unaccent() for diacritic-insensitive matching (cacak → Čačak).
     */
    public function scopeSearch(Builder $query, string $term, ?string $locale = null): Builder
    {
        return $query->whereHas('translations', function ($q) use ($term, $locale) {
            $lowerTerm = '%' . strtolower($term) . '%';

            // Use unaccent for PostgreSQL, plain LOWER for others (SQLite in tests)
            if (config('database.default') === 'pgsql' || config('database.connections.' . config('database.default') . '.driver') === 'pgsql') {
                $q->whereRaw('unaccent(LOWER(name)) LIKE unaccent(?)', [$lowerTerm]);
            } else {
                $q->whereRaw('LOWER(name) LIKE ?', [$lowerTerm]);
            }

            if ($locale) {
                $q->where('locale', $locale);
            }
        });
    }

    /**
     * Helper to get name — always prefer Latin script (sr-Latn) over Cyrillic (sr).
     */
    public function getNameAttribute(): ?string
    {
        // Priority: sr-Latn → en → app locale → any
        $translation = $this->translations->where('locale', 'sr-Latn')->first();

        if (!$translation) {
            $translation = $this->translations->where('locale', 'en')->first();
        }

        if (!$translation) {
            $translation = $this->translations->where('locale', app()->getLocale())->first();
        }

        if (!$translation) {
            $translation = $this->translations->first();
        }

        return $translation?->name;
    }
}