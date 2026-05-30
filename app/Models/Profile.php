<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'bio',
        'avatar_url',
        'cover_url',
        'hometown_city',
        'hometown_country',
        'current_city',
        'current_country',
        'coordinates',
        'interests',
        'hometown_place_id',
        'current_place_id',
    ];

    protected function casts(): array
    {
        return [
            'coordinates' => 'array',
            'interests' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hometownPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class , 'hometown_place_id');
    }

    public function currentPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class , 'current_place_id');
    }

    // === Helper Methods ===

    public function getHometownAttribute(): ?string
    {
        if ($this->hometownPlace) {
            return $this->hometownPlace->name . ', ' . $this->hometown_country; // Fallback country string for now
        }

        if ($this->hometown_city && $this->hometown_country) {
            return "{$this->hometown_city}, {$this->hometown_country}";
        }
        return $this->hometown_city ?? $this->hometown_country;
    }

    public function getCurrentLocationAttribute(): ?string
    {
        if ($this->currentPlace) {
            return $this->currentPlace->name . ', ' . $this->current_country;
        }

        if ($this->current_city && $this->current_country) {
            return "{$this->current_city}, {$this->current_country}";
        }
        return $this->current_city ?? $this->current_country;
    }
}