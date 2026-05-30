<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardMember extends Model
{

    protected $table = 'board_members';
    public $incrementing = false;

    // Composite primary key — override getKeyName
    protected $primaryKey = ['board_id', 'user_id'];

    protected $fillable = [
        'board_id',
        'user_id',
        'role',
    ];

    // === Relationships ===

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // === Helpers ===

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isModerator(): bool
    {
        return $this->role === 'moderator';
    }

    public function canManage(): bool
    {
        return in_array($this->role, ['admin', 'moderator']);
    }

    // Override for composite PK
    protected function setKeysForSaveQuery($query)
    {
        return $query
            ->where('board_id', $this->getAttribute('board_id'))
            ->where('user_id', $this->getAttribute('user_id'));
    }
}
