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
        'total_coins_collected',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'peak_viewers' => 'integer',
            'total_coins_collected' => 'decimal:2',
        ];
    }

    public function liveStream(): BelongsTo
    {
        return $this->belongsTo(LiveStream::class);
    }

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
