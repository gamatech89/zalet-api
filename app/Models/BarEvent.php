<?php

namespace App\Models;

use App\Domains\Identity\Models\User;

use App\Domains\Streaming\Models\StreamSession;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'bar_id',
        'host_id',
        'title',
        'description',
        'cover_image_url',
        'scheduled_at',
        'started_at',
        'ended_at',
        'stream_session_id',
        'status',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function bar(): BelongsTo
    {
        return $this->belongsTo(Bar::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function streamSession(): BelongsTo
    {
        return $this->belongsTo(StreamSession::class);
    }

    /**
     * Check if event is live
     */
    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    /**
     * Check if event is upcoming
     */
    public function isUpcoming(): bool
    {
        return $this->status === 'scheduled' && $this->scheduled_at->isFuture();
    }

    /**
     * Start the event (go live)
     */
    public function start(?int $streamSessionId = null): void
    {
        $this->status = 'live';
        $this->started_at = now();
        $this->stream_session_id = $streamSessionId;
        $this->save();
    }

    /**
     * End the event
     */
    public function end(): void
    {
        $this->status = 'ended';
        $this->ended_at = now();
        $this->save();
    }

    /**
     * Cancel the event
     */
    public function cancel(): void
    {
        $this->status = 'cancelled';
        $this->save();
    }

    /**
     * Get duration in minutes (if ended)
     */
    public function getDurationInMinutes(): ?int
    {
        if (!$this->started_at || !$this->ended_at) {
            return null;
        }

        return $this->started_at->diffInMinutes($this->ended_at);
    }
}
