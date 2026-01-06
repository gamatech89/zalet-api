<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

enum VideoProvider: string
{
    case YouTube = 'youtube';
    case Vimeo = 'vimeo';
    case Mux = 'mux';
    case Local = 'local';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::YouTube => 'YouTube',
            self::Vimeo => 'Vimeo',
            self::Mux => 'Mux',
            self::Local => 'Local',
        };
    }

    /**
     * Get embed URL template.
     */
    public function embedUrlTemplate(): ?string
    {
        return match ($this) {
            self::YouTube => 'https://www.youtube.com/embed/{id}',
            self::Vimeo => 'https://player.vimeo.com/video/{id}',
            self::Mux => 'https://stream.mux.com/{id}.m3u8',
            self::Local => null,
        };
    }

    /**
     * Get thumbnail URL template.
     */
    public function thumbnailUrlTemplate(): ?string
    {
        return match ($this) {
            self::YouTube => 'https://img.youtube.com/vi/{id}/maxresdefault.jpg',
            self::Vimeo => null, // Requires API call
            self::Mux => 'https://image.mux.com/{id}/thumbnail.jpg',
            self::Local => null,
        };
    }

    /**
     * Generate embed URL for a given provider ID.
     */
    public function getEmbedUrl(string $providerId): ?string
    {
        $template = $this->embedUrlTemplate();

        return $template ? str_replace('{id}', $providerId, $template) : null;
    }

    /**
     * Generate thumbnail URL for a given provider ID.
     */
    public function getThumbnailUrl(string $providerId): ?string
    {
        $template = $this->thumbnailUrlTemplate();

        return $template ? str_replace('{id}', $providerId, $template) : null;
    }
}
