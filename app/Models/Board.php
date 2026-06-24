<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Board extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'country_code',
        'city',
        'description',
        'rules',
        'image_url',
        'is_active',
        'is_public',
        'is_featured',
        'conversation_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'is_featured' => 'boolean',
            'rules' => 'array',
        ];
    }

    // === Scopes ===

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // === Relationships ===

    public function posts(): HasMany
    {
        return $this->hasMany(BoardPost::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(BoardMember::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(BoardCategory::class);
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(BoardJoinRequest::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    // === Helpers ===

    /**
     * Get the user's role in this board (or null if not a member).
     */
    public function userRole(string $userId): ?string
    {
        $member = $this->members()->where('user_id', $userId)->first();
        return $member?->role;
    }

    /**
     * Check if a user can manage this board (admin or moderator).
     */
    public function userCanManage(string $userId): bool
    {
        return $this->members()
            ->where('user_id', $userId)
            ->whereIn('role', ['admin', 'moderator'])
            ->exists();
    }

    /**
     * Check if a user is an admin of this board.
     */
    public function userIsAdmin(string $userId): bool
    {
        return $this->members()
            ->where('user_id', $userId)
            ->where('role', 'admin')
            ->exists();
    }
}