<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use App\Domains\Duel\Models\Conversation;
use App\Domains\Shared\Enums\UserRole;
use App\Domains\Shared\Traits\HasUuid;
use App\Domains\Wallet\Models\Wallet;
use App\Models\Bar;
use App\Models\BarMember;
use App\Models\UserLevel;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $uuid
 * @property string $email
 * @property string $password
 * @property UserRole $role
 * @property \Carbon\Carbon|null $email_verified_at
 * @property string|null $remember_token
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Profile|null $profile
 * @property-read Wallet|null $wallet
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasApiTokens;
    use HasUuid;
    use Notifiable;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'email',
        'password',
        'role',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /**
     * Get the user's profile.
     *
     * @return HasOne<Profile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * Get the user's wallet.
     *
     * @return HasOne<Wallet, $this>
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * Get users this user is following.
     *
     * @return HasMany<Follow, $this>
     */
    public function following(): HasMany
    {
        return $this->hasMany(Follow::class, 'follower_id');
    }

    /**
     * Get users following this user.
     *
     * @return HasMany<Follow, $this>
     */
    public function followers(): HasMany
    {
        return $this->hasMany(Follow::class, 'following_id');
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    /**
     * Check if user can create content.
     */
    public function canCreateContent(): bool
    {
        return $this->role->canCreateContent();
    }

    /**
     * Check if user can create public chat rooms.
     */
    public function canCreatePublicRooms(): bool
    {
        return $this->role->canCreatePublicRooms();
    }

    /**
     * Check if user can moderate.
     */
    public function canModerate(): bool
    {
        return $this->role->canModerate();
    }

    /**
     * Get user's DM conversations.
     *
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Get user's notifications.
     *
     * @return HasMany<Notification, $this>
     */
    public function appNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get unread notifications count.
     */
    public function unreadNotificationsCount(): int
    {
        return $this->appNotifications()->unread()->count();
    }

    /**
     * Get user's level info.
     *
     * @return HasOne<UserLevel, $this>
     */
    public function level(): HasOne
    {
        return $this->hasOne(UserLevel::class);
    }

    /**
     * Get bars owned by this user.
     *
     * @return HasMany<Bar, $this>
     */
    public function ownedBars(): HasMany
    {
        return $this->hasMany(Bar::class, 'owner_id');
    }

    /**
     * Get bar memberships.
     *
     * @return HasMany<BarMember, $this>
     */
    public function barMemberships(): HasMany
    {
        return $this->hasMany(BarMember::class);
    }
}
