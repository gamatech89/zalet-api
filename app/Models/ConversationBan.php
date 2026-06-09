<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationBan extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'banned_by',
        'reason',
        'banned_at',
    ];

    protected function casts(): array
    {
        return [
            'banned_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bannedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by');
    }
}
