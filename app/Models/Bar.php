<?php

namespace App\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Bar extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'cover_image_url',
        'owner_id',
        'is_public',
        'password',
        'member_limit',
        'member_count',
        'is_active',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'member_limit' => 'integer',
        'member_count' => 'integer',
    ];

    protected $hidden = [
        'password',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($bar) {
            if (empty($bar->slug)) {
                $bar->slug = Str::slug($bar->name);
                
                // Ensure unique slug
                $originalSlug = $bar->slug;
                $count = 1;
                while (static::where('slug', $bar->slug)->exists()) {
                    $bar->slug = $originalSlug . '-' . $count++;
                }
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(BarMember::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(BarMessage::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(BarEvent::class);
    }

    /**
     * Get all users who are members of this bar
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'bar_members')
            ->withPivot('role', 'joined_at', 'muted_until')
            ->withTimestamps();
    }

    /**
     * Check if a user is a member of this bar
     */
    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Check if a user is owner or moderator
     */
    public function isModeratorOrOwner(User $user): bool
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->whereIn('role', ['owner', 'moderator'])
            ->exists();
    }

    /**
     * Check if bar is full
     */
    public function isFull(): bool
    {
        return $this->member_count >= $this->member_limit;
    }

    /**
     * Check if bar requires password
     */
    public function requiresPassword(): bool
    {
        return !$this->is_public && !empty($this->password);
    }

    /**
     * Increment member count
     */
    public function incrementMemberCount(): void
    {
        $this->increment('member_count');
    }

    /**
     * Decrement member count
     */
    public function decrementMemberCount(): void
    {
        $this->decrement('member_count');
    }

    /**
     * Get the current live event if any
     */
    public function getLiveEvent(): ?BarEvent
    {
        return $this->events()->where('status', 'live')->first();
    }

    /**
     * Get upcoming scheduled events
     */
    public function getUpcomingEvents()
    {
        return $this->events()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at')
            ->get();
    }
}
