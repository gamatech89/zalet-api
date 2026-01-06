<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

enum PostType: string
{
    case Video = 'video';
    case ShortClip = 'short_clip';
    case Image = 'image';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Video => 'Video',
            self::ShortClip => 'Short Clip',
            self::Image => 'Image',
        };
    }

    /**
     * Check if this type supports video providers.
     */
    public function supportsProvider(): bool
    {
        return match ($this) {
            self::Video, self::ShortClip => true,
            self::Image => false,
        };
    }
}
