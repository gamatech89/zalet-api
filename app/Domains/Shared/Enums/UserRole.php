<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * User roles enum.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Moderator = 'moderator';
    case Creator = 'creator';
    case User = 'user';

    /**
     * Get all values as array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if role has creator capabilities.
     */
    public function canCreateContent(): bool
    {
        return in_array($this, [self::Admin, self::Moderator, self::Creator], true);
    }

    /**
     * Check if role can create public chat rooms.
     */
    public function canCreatePublicRooms(): bool
    {
        return in_array($this, [self::Admin, self::Moderator, self::Creator], true);
    }

    /**
     * Check if role has moderation capabilities.
     */
    public function canModerate(): bool
    {
        return in_array($this, [self::Admin, self::Moderator], true);
    }

    /**
     * Check if role has admin capabilities.
     */
    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }
}
