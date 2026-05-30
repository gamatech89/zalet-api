<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BoardPost extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'board_id',
        'user_id',
        'title',
        'body',
        'category',
        'type',
        'images',
        'location_label',
        'place_id',
        'is_pinned',
        'is_active',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'is_pinned' => 'boolean',
            'is_active' => 'boolean',
            'likes_count' => 'integer',
            'views_count' => 'integer',
        ];
    }

    // === Scopes ===

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    // === Relationships ===

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BoardPostComment::class, 'post_id');
    }

    public function likes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'board_post_likes', 'post_id', 'user_id')
            ->withTimestamps();
    }

    public function isLikedBy(string $userId): bool
    {
        return $this->likes()->where('user_id', $userId)->exists();
    }
}