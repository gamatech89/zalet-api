<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Board;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LiveStream extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'board_id',
        'title',
        'stream_key',
        'is_live',
        'scheduled_at',
        'chat_enabled',
        'goals',
        'stream_mode',
        'livekit_room_name',
        'recording_url',
        'recording_disk',
        'recording_duration',
        'recording_size',
        'has_recording',
    ];

    protected function casts(): array
    {
        return [
            'is_live' => 'boolean',
            'has_recording' => 'boolean',
            'chat_enabled' => 'boolean',
            'stream_mode' => 'string',
            'scheduled_at' => 'datetime',
            'goals' => 'array',
            'recording_duration' => 'integer',
            'recording_size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LiveStream $stream) {
            if (empty($stream->stream_key)) {
                $stream->stream_key = Str::uuid()->toString();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(StreamSession::class);
    }

    public function currentSession()
    {
        return $this->hasOne(StreamSession::class)
            ->whereNull('end_time')
            ->latest('created_at');
    }

    public function scopeLive($query)
    {
        return $query->where('is_live', true);
    }

    public function scopeByMode($query, ?string $mode)
    {
        if ($mode && in_array($mode, ['scena', 'moments'])) {
            return $query->where('stream_mode', $mode);
        }
        return $query;
    }

    public function isScena(): bool
    {
        return $this->stream_mode === 'scena';
    }

    public function isMoments(): bool
    {
        return $this->stream_mode === 'moments';
    }

    /**
     * Get the full URL for the recording.
     */
    public function getRecordingFullUrlAttribute(): ?string
    {
        if (!$this->has_recording || !$this->recording_url) {
            return null;
        }

        $disk = $this->recording_disk ?? 'recordings';
        return Storage::disk($disk)->url($this->recording_url);
    }

    /**
     * Delete the recording file from storage.
     */
    public function deleteRecording(): void
    {
        if ($this->recording_url) {
            $disk = $this->recording_disk ?? 'recordings';
            Storage::disk($disk)->delete($this->recording_url);
        }

        $this->update([
            'recording_url' => null,
            'recording_disk' => 'local',
            'recording_duration' => null,
            'recording_size' => null,
            'has_recording' => false,
        ]);
    }

    public function goLive(): StreamSession
    {
        $this->update(['is_live' => true]);

        return $this->sessions()->create([
            'start_time' => now(),
        ]);
    }

    public function endStream(): void
    {
        $this->update(['is_live' => false]);

        $this->currentSession?->update([
            'end_time'        => now(),
            'current_viewers' => 0,
        ]);
    }
}
