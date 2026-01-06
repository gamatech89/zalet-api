<?php

declare(strict_types=1);

namespace App\Domains\Streaming\Services;

use App\Domains\Shared\Enums\VideoProvider;

final class VideoParserService
{
    /**
     * YouTube URL patterns.
     *
     * @var array<string>
     */
    private const YOUTUBE_PATTERNS = [
        // Standard: https://www.youtube.com/watch?v=VIDEO_ID
        '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?(?:.*&)?v=([a-zA-Z0-9_-]{11})/',
        // Short: https://youtu.be/VIDEO_ID
        '/(?:https?:\/\/)?youtu\.be\/([a-zA-Z0-9_-]{11})/',
        // Embed: https://www.youtube.com/embed/VIDEO_ID
        '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
        // Shorts: https://www.youtube.com/shorts/VIDEO_ID
        '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/',
        // Live: https://www.youtube.com/live/VIDEO_ID
        '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/live\/([a-zA-Z0-9_-]{11})/',
    ];

    /**
     * Vimeo URL patterns.
     *
     * @var array<string>
     */
    private const VIMEO_PATTERNS = [
        // Standard: https://vimeo.com/VIDEO_ID
        '/(?:https?:\/\/)?(?:www\.)?vimeo\.com\/(\d+)/',
        // Player: https://player.vimeo.com/video/VIDEO_ID
        '/(?:https?:\/\/)?player\.vimeo\.com\/video\/(\d+)/',
    ];

    /**
     * Parse a video URL and extract provider and ID.
     *
     * @return array{provider: VideoProvider, id: string}|null
     */
    public function parse(string $url): ?array
    {
        $url = trim($url);

        // Try YouTube patterns
        foreach (self::YOUTUBE_PATTERNS as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return [
                    'provider' => VideoProvider::YouTube,
                    'id' => $matches[1],
                ];
            }
        }

        // Try Vimeo patterns
        foreach (self::VIMEO_PATTERNS as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return [
                    'provider' => VideoProvider::Vimeo,
                    'id' => $matches[1],
                ];
            }
        }

        return null;
    }

    /**
     * Check if a URL is a supported video URL.
     */
    public function isSupported(string $url): bool
    {
        return $this->parse($url) !== null;
    }

    /**
     * Get the provider from a URL.
     */
    public function getProvider(string $url): ?VideoProvider
    {
        $result = $this->parse($url);

        return $result['provider'] ?? null;
    }

    /**
     * Get the provider ID from a URL.
     */
    public function getProviderId(string $url): ?string
    {
        $result = $this->parse($url);

        return $result['id'] ?? null;
    }

    /**
     * Generate thumbnail URL for a video URL.
     */
    public function getThumbnailUrl(string $url): ?string
    {
        $result = $this->parse($url);

        if ($result === null) {
            return null;
        }

        return $result['provider']->getThumbnailUrl($result['id']);
    }

    /**
     * Generate embed URL for a video URL.
     */
    public function getEmbedUrl(string $url): ?string
    {
        $result = $this->parse($url);

        if ($result === null) {
            return null;
        }

        return $result['provider']->getEmbedUrl($result['id']);
    }

    /**
     * Validate that a URL is a valid video URL from a supported provider.
     *
     * @return array{valid: bool, provider?: VideoProvider, id?: string, error?: string}
     */
    public function validate(string $url): array
    {
        if (empty($url)) {
            return ['valid' => false, 'error' => 'URL is required'];
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'error' => 'Invalid URL format'];
        }

        $result = $this->parse($url);

        if ($result === null) {
            return ['valid' => false, 'error' => 'Unsupported video provider. Use YouTube or Vimeo.'];
        }

        return [
            'valid' => true,
            'provider' => $result['provider'],
            'id' => $result['id'],
        ];
    }
}
