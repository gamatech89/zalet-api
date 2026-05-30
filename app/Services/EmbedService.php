<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class EmbedService
{
    /**
     * Supported providers and their URL patterns.
     */
    protected array $patterns = [
        'youtube' => [
            '#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([a-zA-Z0-9_-]{11})#',
        ],
        'vimeo' => [
            '#vimeo\.com/(\d+)#',
        ],
        'dailymotion' => [
            '#dailymotion\.com/video/([a-zA-Z0-9]+)#',
        ],
    ];

    /**
     * Detect provider from URL.
     */
    public function detectProvider(string $url): ?string
    {
        foreach ($this->patterns as $provider => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $url)) {
                    return $provider;
                }
            }
        }

        return null;
    }

    /**
     * Extract video ID from URL.
     */
    public function extractVideoId(string $url, string $provider): ?string
    {
        if (!isset($this->patterns[$provider])) {
            return null;
        }

        foreach ($this->patterns[$provider] as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1] ?? null;
            }
        }

        return null;
    }

    /**
     * Check if URL is valid for any supported provider.
     */
    public function isValidUrl(string $url): bool
    {
        return $this->detectProvider($url) !== null;
    }

    /**
     * Get embed URL for provider.
     */
    public function getEmbedUrl(string $url): ?string
    {
        $provider = $this->detectProvider($url);
        if (!$provider) {
            return null;
        }

        $videoId = $this->extractVideoId($url, $provider);
        if (!$videoId) {
            return null;
        }

        return match ($provider) {
            'youtube' => "https://www.youtube.com/embed/{$videoId}",
            'vimeo' => "https://player.vimeo.com/video/{$videoId}",
            'dailymotion' => "https://www.dailymotion.com/embed/video/{$videoId}",
            default => null,
        };
    }

    /**
     * Get thumbnail URL for provider.
     */
    public function getThumbnailUrl(string $url): ?string
    {
        $provider = $this->detectProvider($url);
        if (!$provider) {
            return null;
        }

        $videoId = $this->extractVideoId($url, $provider);
        if (!$videoId) {
            return null;
        }

        return match ($provider) {
            'youtube'     => "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg",
            'vimeo'       => $this->vimeoThumbnail($videoId),
            'dailymotion' => "https://www.dailymotion.com/thumbnail/video/{$videoId}",
            default       => null,
        };
    }

    /**
     * Fetch Vimeo thumbnail via free oEmbed API. Result cached for 24 h.
     */
    protected function vimeoThumbnail(string $videoId): ?string
    {
        return Cache::remember("vimeo_thumb_{$videoId}", 86400, function () use ($videoId) {
            try {
                $response = Http::timeout(5)
                    ->get('https://vimeo.com/api/oembed.json', [
                        'url' => "https://vimeo.com/{$videoId}",
                    ]);

                if ($response->successful()) {
                    return $response->json('thumbnail_url');
                }
            } catch (\Throwable) {
                // Network failure — return null, will be retried next time
            }

            return null;
        });
    }

    /**
     * Extract all metadata from URL.
     */
    public function extractMetadata(string $url): ?array
    {
        $provider = $this->detectProvider($url);
        if (!$provider) {
            return null;
        }

        return [
            'provider' => $provider,
            'video_id' => $this->extractVideoId($url, $provider),
            'embed_url' => $this->getEmbedUrl($url),
            'thumbnail_url' => $this->getThumbnailUrl($url),
        ];
    }
}
