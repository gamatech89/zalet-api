<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardPostComment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'post_id',
        'user_id',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'likes_count' => 'integer',
        ];
    }

    // === Relationships ===

    public function post(): BelongsTo
    {
        return $this->belongsTo(BoardPost::class , 'post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}