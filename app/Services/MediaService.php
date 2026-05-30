<?php

namespace App\Services;

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
    /**
     * Store a video file and create media record.
     */
    public function storeVideo(
        User $user,
        UploadedFile $file,
        string $type,
        ?string $title = null,
        ?string $description = null,
        bool $isPpv = false,
        ?float $priceCoins = null,
        string $accessLevel = 'free',
        ?int $requiredPlanLevel = null
    ): Media {
        $fileSize = $file->getSize();

        // Check storage quota
        if (!$user->hasStorageFor($fileSize)) {
            throw new \RuntimeException('Insufficient storage quota. Please upgrade or delete some content.');
        }

        // Generate unique filename
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = "videos/{$user->id}/{$filename}";

        // Store the file
        Storage::disk('media')->put($path, file_get_contents($file->getRealPath()));

        // Create media record
        $media = Media::create([
            'user_id' => $user->id,
            'type' => $type,
            'provider' => 'native',
            'url' => $path,
            'title' => $title ?? $file->getClientOriginalName(),
            'description' => $description,
            'size_bytes' => $fileSize,
            'is_ppv' => $isPpv,
            'price_coins' => $isPpv ? $priceCoins : null,
            'access_level' => $accessLevel,
            'required_plan_level' => $requiredPlanLevel,
        ]);

        // Update user's storage used
        $user->increment('storage_used_bytes', $fileSize);

        return $media;
    }

    /**
     * Delete media and free storage.
     */
    public function deleteMedia(Media $media): void
    {
        // Only delete file for native content
        if ($media->provider === 'native' && $media->url) {
            Storage::disk('media')->delete($media->url);

            // Decrement user's storage
            $media->user->decrement('storage_used_bytes', $media->size_bytes);
        }

        $media->delete();
    }

    /**
     * Get URL for media file.
     */
    public function getMediaUrl(Media $media): ?string
    {
        if ($media->provider !== 'native') {
            return $media->url;
        }

        return Storage::disk('media')->url($media->url);
    }
}
