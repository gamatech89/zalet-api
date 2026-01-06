<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $city
 * @property string $country
 * @property string $country_code
 * @property float|null $latitude
 * @property float|null $longitude
 * @property \Carbon\Carbon $created_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Profile> $originProfiles
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Profile> $currentProfiles
 */
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): LocationFactory
    {
        return LocationFactory::new();
    }

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'city',
        'country',
        'country_code',
        'latitude',
        'longitude',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get profiles where this is the origin location.
     *
     * @return HasMany<Profile, $this>
     */
    public function originProfiles(): HasMany
    {
        return $this->hasMany(Profile::class, 'origin_location_id');
    }

    /**
     * Get profiles where this is the current location.
     *
     * @return HasMany<Profile, $this>
     */
    public function currentProfiles(): HasMany
    {
        return $this->hasMany(Profile::class, 'current_location_id');
    }

    /**
     * Get formatted location string.
     */
    public function getFullName(): string
    {
        return "{$this->city}, {$this->country}";
    }

    /**
     * Scope to search by city name.
     *
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where('city', 'ilike', "%{$search}%")
            ->orWhere('country', 'ilike', "%{$search}%");
    }
}
