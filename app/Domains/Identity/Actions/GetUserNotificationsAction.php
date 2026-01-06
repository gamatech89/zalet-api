<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\Notification;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Enums\NotificationType;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Get notifications for a user with filtering options.
 */
final class GetUserNotificationsAction
{
    /**
     * Get paginated notifications for a user.
     *
     * @return LengthAwarePaginator<int, Notification>
     */
    public function execute(
        User $user,
        ?bool $unreadOnly = false,
        ?NotificationType $type = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = $user->appNotifications()
            ->with(['actor.profile'])
            ->orderByDesc('created_at');

        if ($unreadOnly) {
            $query->unread();
        }

        if ($type !== null) {
            $query->ofType($type);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get unread count for a user.
     */
    public function unreadCount(User $user): int
    {
        return $user->appNotifications()->unread()->count();
    }

    /**
     * Get count by type for a user.
     *
     * @return array<string, int>
     */
    public function countByType(User $user, bool $unreadOnly = true): array
    {
        $query = $user->appNotifications();

        if ($unreadOnly) {
            $query->unread();
        }

        return $query
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();
    }
}
