<?php

namespace App\Domains\Streaming\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreamSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'category',
        'thumbnail_url',
        'room_name',
        'is_public',
        'status',
        'viewer_count',
        'peak_viewers',
        'total_viewers',
        'started_at',
        'ended_at',
        'duration_seconds',
        'metadata',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'viewer_count' => 'integer',
        'peak_viewers' => 'integer',
        'total_viewers' => 'integer',
        'duration_seconds' => 'integer',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the streamer (user who owns this stream)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Alias for user() for clarity
     */
    public function streamer(): BelongsTo
    {
        return $this->user();
    }

    /**
     * Check if stream is live
     */
    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    /**
     * Check if stream has ended
     */
    public function hasEnded(): bool
    {
        return in_array($this->status, ['ended', 'cancelled']);
    }

    /**
     * Update viewer count and track peak
     */
    public function updateViewerCount(int $count): void
    {
        $this->viewer_count = $count;
        
        if ($count > $this->peak_viewers) {
            $this->peak_viewers = $count;
        }
        
        $this->save();
    }

    /**
     * End the stream and calculate duration
     */
    public function end(): void
    {
        $this->status = 'ended';
        $this->ended_at = now();
        
        if ($this->started_at) {
            $this->duration_seconds = $this->started_at->diffInSeconds($this->ended_at);
        }
        
        $this->save();
    }

    /**
     * Scope for live streams
     */
    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }

    /**
     * Scope for public streams
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }
}
