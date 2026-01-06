<?php

declare(strict_types=1);

namespace App\Domains\Duel\Enums;

enum ChatRoomType: string
{
    case PUBLIC_KAFANA = 'public_kafana';
    case PRIVATE = 'private';
    case DUEL = 'duel';
    case DIRECT_MESSAGE = 'direct_message';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::PUBLIC_KAFANA => 'Javna Kafana',
            self::PRIVATE => 'Privatna Soba',
            self::DUEL => 'Duel Arena',
            self::DIRECT_MESSAGE => 'Privatna Poruka',
        };
    }

    /**
     * Check if this type requires special permissions to create.
     */
    public function requiresPermission(): bool
    {
        return $this === self::PUBLIC_KAFANA;
    }

    /**
     * Check if this is a direct message conversation.
     */
    public function isDirectMessage(): bool
    {
        return $this === self::DIRECT_MESSAGE;
    }

    /**
     * Check if this is a public room visible in listings.
     */
    public function isPublic(): bool
    {
        return $this === self::PUBLIC_KAFANA;
    }
}
