<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\DTOs\LoginUserDTO;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class LoginUserAction
{
    /**
     * Authenticate user and return token.
     *
     * @return array{user: User, token: string}
     * @throws ValidationException
     */
    public function execute(LoginUserDTO $dto): array
    {
        $user = User::where('email', $dto->email)->first();

        if (! $user || ! Hash::check($dto->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // Delete existing tokens if single session mode
        if ($dto->singleSession) {
            $user->tokens()->delete();
        }

        $token = $user->createToken(
            name: $dto->deviceName,
            abilities: $this->getAbilitiesForRole($user),
        )->plainTextToken;

        $user->load(['profile.originLocation', 'profile.currentLocation', 'wallet']);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * @return array<string>
     */
    private function getAbilitiesForRole(User $user): array
    {
        return match ($user->role->value) {
            'admin' => ['*'],
            'creator' => ['read', 'write', 'stream', 'host-duel'],
            default => ['read', 'write'],
        };
    }
}
