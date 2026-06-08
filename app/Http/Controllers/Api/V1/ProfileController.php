<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UploadAvatarRequest;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Get the authenticated user's profile.
     *
     * GET /api/v1/profile
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Ensure profile exists (auto-create if missing)
        $profile = $user->profile ?? $user->profile()->create([]);

        return response()->json([
            'profile' => $profile,
            'computed' => [
                'hometown' => $profile->hometown,
                'current_location' => $profile->current_location,
            ],
        ]);
    }

    /**
     * Update the authenticated user's profile.
     *
     * PUT /api/v1/profile
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        
        // Ensure profile exists
        $profile = $user->profile ?? $user->profile()->create([]);

        $profile->update($request->validated());

        return response()->json([
            'message' => 'Profile updated successfully.',
            'profile' => $profile->fresh(),
            'computed' => [
                'hometown' => $profile->hometown,
                'current_location' => $profile->current_location,
            ],
        ]);
    }

    /**
     * Upload avatar for the authenticated user.
     *
     * POST /api/v1/profile/avatar
     */
    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = $request->user();
        
        // Ensure profile exists
        $profile = $user->profile ?? $user->profile()->create([]);

        // Delete old avatar if exists
        if ($profile->avatar_url) {
            $oldPath = ltrim(str_replace(Storage::url(''), '', $profile->avatar_url), '/');
            Storage::delete($oldPath);
        }

        // Store new avatar on default disk (s3 in production)
        $path = $request->file('avatar')->store("avatars/{$user->id}");

        // Update profile with full URL
        $profile->update([
            'avatar_url' => Storage::url($path),
        ]);

        return response()->json([
            'message' => 'Avatar uploaded successfully.',
            'avatar_url' => $profile->avatar_url,
        ]);
    }

    /**
     * Upload cover/banner image for the authenticated user.
     *
     * POST /api/v1/profile/cover
     */
    public function uploadCover(Request $request): JsonResponse
    {
        $request->validate([
            'cover' => 'required|image|max:5120', // 5MB max
        ]);

        $user = $request->user();
        $profile = $user->profile ?? $user->profile()->create([]);

        // Delete old cover if exists
        if ($profile->cover_url) {
            $oldPath = ltrim(str_replace(Storage::url(''), '', $profile->cover_url), '/');
            Storage::delete($oldPath);
        }

        // Store new cover on default disk (s3 in production)
        $path = $request->file('cover')->store("covers/{$user->id}");

        $profile->update([
            'cover_url' => Storage::url($path),
        ]);

        return response()->json([
            'message' => 'Cover uploaded successfully.',
            'cover_url' => $profile->cover_url,
        ]);
    }
}
