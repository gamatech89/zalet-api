<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\VideoProvider;
use App\Domains\Streaming\Services\VideoParserService;

describe('VideoParserService', function (): void {

    beforeEach(function (): void {
        $this->service = new VideoParserService();
    });

    describe('YouTube parsing', function (): void {

        it('parses standard youtube watch url', function (): void {
            $result = $this->service->parse('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

            expect($result)->not->toBeNull()
                ->and($result['provider'])->toBe(VideoProvider::YouTube)
                ->and($result['id'])->toBe('dQw4w9WgXcQ');
        });

        it('parses youtube short url', function (): void {
            $result = $this->service->parse('https://youtu.be/dQw4w9WgXcQ');

            expect($result)->not->toBeNull()
                ->and($result['provider'])->toBe(VideoProvider::YouTube)
                ->and($result['id'])->toBe('dQw4w9WgXcQ');
        });

        it('parses youtube embed url', function (): void {
            $result = $this->service->parse('https://www.youtube.com/embed/dQw4w9WgXcQ');

            expect($result)->not->toBeNull()
                ->and($result['provider'])->toBe(VideoProvider::YouTube)
                ->and($result['id'])->toBe('dQw4w9WgXcQ');
        });

        it('parses youtube shorts url', function (): void {
            $result = $this->service->parse('https://www.youtube.com/shorts/dQw4w9WgXcQ');

            expect($result)->not->toBeNull()
                ->and($result['provider'])->toBe(VideoProvider::YouTube)
                ->and($result['id'])->toBe('dQw4w9WgXcQ');
        });

        it('parses youtube live url', function (): void {
            $result = $this->service->parse('https://www.youtube.com/live/dQw4w9WgXcQ');

            expect($result)->not->toBeNull()
                ->and($result['provider'])->toBe(VideoProvider::YouTube)
                ->and($result['id'])->toBe('dQw4w9WgXcQ');
        });

        it('parses youtube url with timestamp', function (): void {
            $result = $this->service->parse('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=120');

            expect($result)->not->toBeNull()
                ->and($result['id'])->toBe('dQw4w9WgXcQ');
        });

        it('parses youtube url with playlist', function (): void {
            $result = $this->service->parse('https://www.youtube.com/watch?v=dQw4w9WgXcQ&list=PLrAXtmErZgOeiKm4sgNOknGvNjby9efdf');

            expect($result)->not->toBeNull()
                ->and($result['id'])->toBe('dQw4w9WgXcQ');
        });

        it('generates youtube thumbnail url', function (): void {
            $thumbnail = $this->service->getThumbnailUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

            expect($thumbnail)->toBe('https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg');
        });

        it('generates youtube embed url', function (): void {
            $embed = $this->service->getEmbedUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

            expect($embed)->toBe('https://www.youtube.com/embed/dQw4w9WgXcQ');
        });

    });

    describe('Vimeo parsing', function (): void {

        it('parses standard vimeo url', function (): void {
            $result = $this->service->parse('https://vimeo.com/123456789');

            expect($result)->not->toBeNull()
                ->and($result['provider'])->toBe(VideoProvider::Vimeo)
                ->and($result['id'])->toBe('123456789');
        });

        it('parses vimeo player url', function (): void {
            $result = $this->service->parse('https://player.vimeo.com/video/123456789');

            expect($result)->not->toBeNull()
                ->and($result['provider'])->toBe(VideoProvider::Vimeo)
                ->and($result['id'])->toBe('123456789');
        });

        it('parses vimeo url with hash', function (): void {
            $result = $this->service->parse('https://vimeo.com/123456789/abcdef');

            expect($result)->not->toBeNull()
                ->and($result['id'])->toBe('123456789');
        });

        it('generates vimeo embed url', function (): void {
            $embed = $this->service->getEmbedUrl('https://vimeo.com/123456789');

            expect($embed)->toBe('https://player.vimeo.com/video/123456789');
        });

    });

    describe('Validation', function (): void {

        it('validates supported youtube url', function (): void {
            expect($this->service->isSupported('https://www.youtube.com/watch?v=dQw4w9WgXcQ'))
                ->toBeTrue();
        });

        it('validates supported vimeo url', function (): void {
            expect($this->service->isSupported('https://vimeo.com/123456789'))
                ->toBeTrue();
        });

        it('returns false for unsupported url', function (): void {
            expect($this->service->isSupported('https://dailymotion.com/video/x123456'))
                ->toBeFalse();
        });

        it('returns false for invalid url', function (): void {
            expect($this->service->isSupported('not-a-url'))
                ->toBeFalse();
        });

        it('validates url correctly', function (): void {
            $validResult = $this->service->validate('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
            $invalidResult = $this->service->validate('invalid');

            expect($validResult['valid'])->toBeTrue()
                ->and($validResult)->toHaveKey('provider')
                ->and($validResult)->toHaveKey('id')
                ->and($invalidResult['valid'])->toBeFalse()
                ->and($invalidResult)->toHaveKey('error');
        });

    });

    describe('Provider detection', function (): void {

        it('detects youtube provider', function (): void {
            expect($this->service->getProvider('https://www.youtube.com/watch?v=dQw4w9WgXcQ'))
                ->toBe(VideoProvider::YouTube);
        });

        it('detects vimeo provider', function (): void {
            expect($this->service->getProvider('https://vimeo.com/123456789'))
                ->toBe(VideoProvider::Vimeo);
        });

        it('returns null for unsupported provider', function (): void {
            expect($this->service->getProvider('https://example.com/video'))
                ->toBeNull();
        });

    });

});
