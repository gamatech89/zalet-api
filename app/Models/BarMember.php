<?php

namespace App\Models;

use App\Domains\Identity\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'bar_id',
        'user_id',
        'role',
        'joined_at',
        'muted_until',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'muted_until' => 'datetime',
    ];

    public function bar(): BelongsTo
    {
        return $this->belongsTo(Bar::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if member is muted
     */
    public function isMuted(): bool
    {
        return $this->muted_until && $this->muted_until->isFuture();
    }

    /**
     * Check if member is owner
     */
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /**
     * Check if member is moderator or owner
     */
    public function canModerate(): bool
    {
        return in_array($this->role, ['owner', 'moderator']);
    }

    /**
     * Mute the member for a duration
     */
    public function mute(int $minutes): void
    {
        $this->muted_until = now()->addMinutes($minutes);
        $this->save();
    }

    /**
     * Unmute the member
     */
    public function unmute(): void
    {
        $this->muted_until = null;
        $this->save();
    }

    /**
     * Promote to moderator
     */
    public function promoteToModerator(): void
    {
        if ($this->role === 'member') {
            $this->role = 'moderator';
            $this->save();
        }
    }

    /**
     * Demote to member
     */
    public function demoteToMember(): void
    {
        if ($this->role === 'moderator') {
            $this->role = 'member';
            $this->save();
        }
    }
}
