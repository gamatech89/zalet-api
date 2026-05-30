<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceTranslation extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'place_id',
        'locale',
        'name',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }
}
