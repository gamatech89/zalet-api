<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class GetUsersByLocationAction
{
    /**
     * Get users by origin or current location.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function execute(
        int $locationId,
        string $locationType = 'origin', // 'origin' or 'current'
        ?User $excludeUser = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $locationColumn = $locationType === 'current'
            ? 'current_location_id'
            : 'origin_location_id';

        return User::query()
            ->select('users.*')
            ->join('profiles', 'profiles.user_id', '=', 'users.id')
            ->where("profiles.{$locationColumn}", $locationId)
            ->where('profiles.is_private', false)
            ->when($excludeUser !== null, function (Builder $query) use ($excludeUser): void {
                /** @var User $excludeUser */
                $query->where('users.id', '!=', $excludeUser->id);
            })
            ->with(['profile.originLocation', 'profile.currentLocation'])
            ->orderByDesc('users.created_at')
            ->paginate($perPage);
    }
}
