<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinPackage extends Model
{
    protected $fillable = [
        'coins',
        'bonus',
        'price_rsd',
        'label',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'coins' => 'integer',
        'bonus' => 'integer',
        'price_rsd' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
