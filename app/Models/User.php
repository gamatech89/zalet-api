<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'display_name',
        'avatar_url',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get user's level info
     */
    public function level(): HasOne
    {
        return $this->hasOne(UserLevel::class);
    }

    /**
     * Get bars owned by user
     */
    public function ownedBars(): HasMany
    {
        return $this->hasMany(Bar::class, 'owner_id');
    }

    /**
     * Get bar memberships
     */
    public function barMemberships(): HasMany
    {
        return $this->hasMany(BarMember::class);
    }

    /**
     * Get bars user is member of
     */
    public function bars()
    {
        return $this->belongsToMany(Bar::class, 'bar_members')
            ->withPivot('role', 'joined_at', 'muted_until')
            ->withTimestamps();
    }

    /**
     * Get bar messages sent by user
     */
    public function barMessages(): HasMany
    {
        return $this->hasMany(BarMessage::class);
    }

    /**
     * Get events hosted by user
     */
    public function hostedEvents(): HasMany
    {
        return $this->hasMany(BarEvent::class, 'host_id');
    }
}
