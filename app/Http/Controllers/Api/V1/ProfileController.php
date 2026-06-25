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
    private function ensureProfile($user): Profile
    {
        return $user->profile ?? $user->profile()->firstOrCreate([]);
    }

    /**
     * Get the authenticated user's profile.
     *
     * GET /api/v1/profile
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $this->ensureProfile($user);

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
        $profile = $this->ensureProfile($user);

        $profile->update($request->validated());

        // Update display name on user if provided (rate-limited to once per 24h)
        if ($request->has('name')) {
            $newName = $request->input('name');
            $nameChanged = $newName !== $user->name;

            if ($nameChanged && $user->name_changed_at) {
                $hoursLeft = ceil($user->name_changed_at->diffInMinutes(now()) < 1440
                    ? (1440 - $user->name_changed_at->diffInMinutes(now())) / 60
                    : 0);

                if ($user->name_changed_at->diffInHours(now()) < 24) {
                    $hoursLeft = (int) ceil(24 - $user->name_changed_at->diffInRealHours(now()));
                    return response()->json([
                        'message' => "Nick možeš promeniti jednom u 24 sata. Pokušaj ponovo za {$hoursLeft} " . ($hoursLeft === 1 ? 'sat' : ($hoursLeft < 5 ? 'sata' : 'sati')) . '.',
                    ], 422);
                }
            }

            $user->update([
                'name' => $newName,
                'name_changed_at' => $nameChanged ? now() : $user->name_changed_at,
            ]);
        }

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
        $profile = $this->ensureProfile($user);

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
        $profile = $this->ensureProfile($user);

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
