<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, HasName, MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();
    }

    public function getFilamentName(): string
    {
        return $this->username ?? $this->email;
    }

    protected $fillable = [
        'email',
        'username',
        'name',
        'password',
        'legacy_id',
        'is_legacy_founder',
        'storage_limit_mb',
        'storage_used_bytes',
        'registration_ip',
        'last_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'legacy_id' => 'integer',
            'is_legacy_founder' => 'boolean',
            'storage_limit_mb' => 'integer',
            'storage_used_bytes' => 'integer',
        ];
    }

    // === Relationships ===

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function liveStreams(): HasMany
    {
        return $this->hasMany(LiveStream::class);
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')
            ->withTimestamps();
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')
            ->withTimestamps();
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class)
            ->withPivot('joined_at', 'last_read_at');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function creatorRequests(): HasMany
    {
        return $this->hasMany(CreatorRequest::class);
    }

    // === Subscription Helpers ===

    /**
     * Get the user's current active subscription.
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->active()
            ->with('plan')
            ->first();
    }

    /**
     * Get the user's active subscription plan.
     */
    public function activeSubscriptionPlan(): ?SubscriptionPlan
    {
        return $this->activeSubscription()?->plan;
    }

    /**
     * Check if user has a subscription at or above the given level.
     */
    public function hasSubscriptionLevel(int $requiredLevel): bool
    {
        $plan = $this->activeSubscriptionPlan();
        return $plan !== null && $plan->level >= $requiredLevel;
    }

    // === Role Helpers ===

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isCreator(): bool
    {
        return in_array($this->role, ['creator', 'admin']);
    }

    public function isLegacyFounder(): bool
    {
        return $this->is_legacy_founder === true;
    }

    // === Storage Helpers ===

    public function getStorageRemainingBytes(): int
    {
        return ($this->storage_limit_mb * 1024 * 1024) - $this->storage_used_bytes;
    }

    public function hasStorageFor(int $bytes): bool
    {
        return $this->getStorageRemainingBytes() >= $bytes;
    }

    public function isFollowedBy(string $userId): bool
    {
        return $this->followers()->where('follower_id', $userId)->exists();
    }

    public function isFollowing(string $userId): bool
    {
        return $this->following()->where('following_id', $userId)->exists();
    }
}
