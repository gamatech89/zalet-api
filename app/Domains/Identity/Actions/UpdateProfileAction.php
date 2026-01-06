<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\DTOs\UpdateProfileDTO;
use App\Domains\Identity\Models\Profile;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Actions\Action;

final class UpdateProfileAction extends Action
{
    /**
     * Update user profile.
     */
    public function execute(User $user, UpdateProfileDTO $dto): Profile
    {
        return $this->transaction(function () use ($user, $dto): Profile {
            $profile = $user->profile;

            if (! $profile) {
                throw new \RuntimeException('User profile not found');
            }

            $profile->update(array_filter([
                'username' => $dto->username,
                'display_name' => $dto->displayName,
                'bio' => $dto->bio,
                'avatar_url' => $dto->avatarUrl,
                'origin_location_id' => $dto->originLocationId,
                'current_location_id' => $dto->currentLocationId,
                'is_private' => $dto->isPrivate,
            ], fn ($value) => $value !== null));

            $profile->load(['originLocation', 'currentLocation']);

            return $profile;
        });
    }
}
