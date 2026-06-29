<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreamSession extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'live_stream_id',
        'start_time',
        'end_time',
        'peak_viewers',
        'current_viewers',
        'total_coins_collected',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'peak_viewers' => 'integer',
            'current_viewers' => 'integer',
            'total_coins_collected' => 'decimal:2',
        ];
    }

    public function liveStream(): BelongsTo
    {
        return $this->belongsTo(LiveStream::class);
    }

    /**
     * Called on participant_joined. Increments current_viewers and updates peak if exceeded.
     */
    public function viewerJoined(): void
    {
        $this->increment('current_viewers');
        $this->refresh();
        if ($this->current_viewers > $this->peak_viewers) {
            $this->update(['peak_viewers' => $this->current_viewers]);
        }
    }

    /**
     * Called on participant_left. Decrements current_viewers (min 0).
     */
    public function viewerLeft(): void
    {
        if ($this->current_viewers > 0) {
            $this->decrement('current_viewers');
        }
    }

    /**
     * Legacy method — kept for compatibility.
     */
    public function updatePeakViewers(int $currentViewers): void
    {
        if ($currentViewers > $this->peak_viewers) {
            $this->update(['peak_viewers' => $currentViewers]);
        }
    }

    public function addCoins(float $amount): void
    {
        $this->increment('total_coins_collected', $amount);
    }

    public function getDurationMinutes(): ?int
    {
        if (!$this->end_time) {
            return $this->start_time->diffInMinutes(now());
        }
        return $this->start_time->diffInMinutes($this->end_time);
    }
}
