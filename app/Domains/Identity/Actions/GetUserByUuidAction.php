<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class GetUserByUuidAction
{
    /**
     * Find a user by their public UUID.
     */
    public function execute(string $uuid, bool $withRelations = true): ?User
    {
        $query = User::where('uuid', $uuid);

        if ($withRelations) {
            $query->with(['profile.originLocation', 'profile.currentLocation', 'wallet']);
        }

        return $query->first();
    }
}
