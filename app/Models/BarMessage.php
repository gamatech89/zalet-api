<?php

namespace App\Models;

use App\Domains\Identity\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BarMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'bar_id',
        'user_id',
        'content',
        'reply_to_id',
        'is_deleted',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
    ];

    public function bar(): BelongsTo
    {
        return $this->belongsTo(Bar::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(BarMessage::class, 'reply_to_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(BarMessage::class, 'reply_to_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(BarMessageReaction::class, 'message_id');
    }

    /**
     * Get reactions grouped by emoji with counts
     */
    public function getReactionCounts(): array
    {
        return $this->reactions()
            ->selectRaw('emoji, count(*) as count')
            ->groupBy('emoji')
            ->pluck('count', 'emoji')
            ->toArray();
    }

    /**
     * Check if user has reacted with a specific emoji
     */
    public function hasUserReacted(int $userId, string $emoji): bool
    {
        return $this->reactions()
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->exists();
    }

    /**
     * Soft delete the message (just mark as deleted)
     */
    public function softDelete(): void
    {
        $this->is_deleted = true;
        $this->content = '[Poruka obrisana]';
        $this->save();
    }
}
