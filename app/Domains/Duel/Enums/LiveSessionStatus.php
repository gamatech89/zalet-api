<?php

declare(strict_types=1);

namespace App\Domains\Duel\Enums;

enum LiveSessionStatus: string
{
    case WAITING = 'waiting';
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::WAITING => 'Čeka se',
            self::ACTIVE => 'Uživo',
            self::PAUSED => 'Pauzirano',
            self::COMPLETED => 'Završeno',
            self::CANCELLED => 'Otkazano',
        };
    }

    /**
     * Check if the session can accept new guests.
     */
    public function canJoin(): bool
    {
        return $this === self::WAITING;
    }

    /**
     * Check if the session is in progress.
     */
    public function isInProgress(): bool
    {
        return in_array($this, [self::WAITING, self::ACTIVE, self::PAUSED], true);
    }
}
