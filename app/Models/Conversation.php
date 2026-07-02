<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'is_group',
        'is_public',
        'invite_code',
        'pinned_message_id',
        'entry_price',
    ];

    protected function casts(): array
    {
        return [
            'is_group' => 'boolean',
            'is_public' => 'boolean',
            'entry_price' => 'integer',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('joined_at', 'last_read_at', 'role');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage()
    {
        // Avoid latestOfMany() which uses MAX() — not compatible with UUIDs on PostgreSQL
        return $this->hasOne(Message::class)->orderByDesc('created_at');
    }

    public function bans(): HasMany
    {
        return $this->hasMany(ConversationBan::class);
    }

    public function board(): HasOne
    {
        return $this->hasOne(Board::class, 'conversation_id');
    }

    public function pinnedMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'pinned_message_id');
    }
}
