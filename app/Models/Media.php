<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Media extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'provider',
        'url',
        'title',
        'description',
        'thumbnail_url',
        'size_bytes',
        'is_ppv',
        'price_coins',
        'access_level',
        'required_plan_level',
        'is_approved',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'is_approved' => 'boolean',
            'is_ppv' => 'boolean',
            'price_coins' => 'decimal:2',
            'required_plan_level' => 'integer',
            'views_count' => 'integer',
            'likes_count' => 'integer',
            'comments_count' => 'integer',
        ];
    }

    // === Relationships ===

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(MediaPurchase::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class , 'media_tag');
    }

    /**
     * Check if content requires a specific subscription plan level.
     */
    public function requiresSubscription(): bool
    {
        return $this->required_plan_level !== null && $this->required_plan_level > 0;
    }

    public function likes(): HasMany
    {
        return $this->hasMany(MediaLike::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(MediaBookmark::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(MediaComment::class);
    }

    /**
     * Check if a specific user has liked this media.
     */
    public function likedBy(?User $user): bool
    {
        if (!$user) return false;
        return $this->likes()->where('user_id', $user->id)->exists();
    }

    /**
     * Check if a specific user has bookmarked this media.
     */
    public function bookmarkedBy(?User $user): bool
    {
        if (!$user) return false;
        return $this->bookmarks()->where('user_id', $user->id)->exists();
    }

    // === Scopes ===

    public function scopeWithTag($query, string $tagName)
    {
        return $query->whereHas('tags', function ($q) use ($tagName) {
            $q->where('name', $tagName);
        });
    }

    public function scopeMoments($query)
    {
        return $query->where('type', 'moment');
    }

    public function scopeEmbeds($query)
    {
        return $query->where('type', 'embed');
    }

    public function scopeLongForm($query)
    {
        return $query->where('type', 'long_form');
    }

    public function scopePpv($query)
    {
        return $query->where('is_ppv', true);
    }

    public function scopeFree($query)
    {
        return $query->where('is_ppv', false);
    }

    // === Helper Methods ===

    public function isMoment(): bool
    {
        return $this->type === 'moment';
    }

    public function isEmbed(): bool
    {
        return $this->type === 'embed';
    }

    public function isNative(): bool
    {
        return $this->provider === 'native';
    }

    public function isLocked(): bool
    {
        return $this->is_ppv || $this->access_level !== 'free';
    }
}