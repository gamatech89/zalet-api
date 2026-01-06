<?php

declare(strict_types=1);

namespace App\Domains\Duel\Enums;

enum MessageType: string
{
    case TEXT = 'text';
    case GIFT = 'gift';
    case SYSTEM = 'system';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Tekst',
            self::GIFT => 'Poklon',
            self::SYSTEM => 'Sistemska poruka',
        };
    }
}
