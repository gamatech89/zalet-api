<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BannedIdentifier extends Model
{
    use HasUuids;

    protected $fillable = [
        'type',
        'value',
        'reason',
        'banned_by',
    ];

    protected $casts = [
        'type' => 'string',
    ];

    public function scopeEmails(Builder $query): Builder
    {
        return $query->where('type', 'email');
    }

    public function scopeIps(Builder $query): Builder
    {
        return $query->where('type', 'ip');
    }

    public function scopeEmailDomains(Builder $query): Builder
    {
        return $query->where('type', 'email_domain');
    }

    public function bannedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by');
    }
}
