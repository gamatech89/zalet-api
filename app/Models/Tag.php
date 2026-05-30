<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'label',
        'color',
    ];

    /**
     * Media items that have this tag.
     */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class , 'media_tag');
    }
}