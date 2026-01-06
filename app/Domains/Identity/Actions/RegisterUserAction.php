<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\DTOs\RegisterUserDTO;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Actions\Action;
use App\Domains\Shared\Enums\UserRole;
use App\Domains\Wallet\Models\Wallet;
use Illuminate\Support\Facades\Hash;

final class RegisterUserAction extends Action
{
    /**
     * Register a new user with profile and wallet.
     */
    public function execute(RegisterUserDTO $dto): User
    {
        return $this->transaction(function () use ($dto): User {
            $user = User::create([
                'email' => $dto->email,
                'password' => Hash::make($dto->password),
                'role' => UserRole::User,
            ]);

            $user->profile()->create([
                'username' => $dto->username,
                'display_name' => $dto->displayName,
                'bio' => $dto->bio,
                'origin_location_id' => $dto->originLocationId,
                'current_location_id' => $dto->currentLocationId ?? $dto->originLocationId,
                'is_private' => $dto->isPrivate,
            ]);

            Wallet::create([
                'user_id' => $user->id,
                'balance' => 0,
                'currency' => 'CREDITS',
            ]);

            $user->load(['profile.originLocation', 'profile.currentLocation', 'wallet']);

            return $user;
        });
    }
}
