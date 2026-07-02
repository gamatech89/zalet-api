<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreamUnlock extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'live_stream_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function liveStream(): BelongsTo
    {
        return $this->belongsTo(LiveStream::class);
    }
}
