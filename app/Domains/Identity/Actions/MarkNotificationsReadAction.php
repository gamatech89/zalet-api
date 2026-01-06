<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Events\NotificationsRead;
use App\Domains\Identity\Models\Notification;
use App\Domains\Identity\Models\User;

/**
 * Mark notifications as read.
 */
final class MarkNotificationsReadAction
{
    /**
     * Mark a single notification as read.
     */
    public function execute(Notification $notification, bool $broadcast = true): Notification
    {
        $notification->markAsRead();

        if ($broadcast) {
            $unreadCount = $notification->user->unreadNotificationsCount();
            NotificationsRead::dispatch(
                $notification->user_id,
                [$notification->uuid],
                $unreadCount,
            );
        }

        return $notification;
    }

    /**
     * Mark multiple notifications as read by IDs.
     *
     * @param array<string> $uuids
     */
    public function markMultiple(User $user, array $uuids, bool $broadcast = true): int
    {
        $count = $user->appNotifications()
            ->whereIn('uuid', $uuids)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($broadcast && $count > 0) {
            NotificationsRead::dispatch(
                $user->id,
                $uuids,
                $user->unreadNotificationsCount(),
            );
        }

        return $count;
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllRead(User $user, bool $broadcast = true): int
    {
        $count = $user->appNotifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($broadcast && $count > 0) {
            NotificationsRead::dispatch(
                $user->id,
                [], // Empty array indicates "all marked as read"
                0,  // All read means 0 unread
            );
        }

        return $count;
    }
}
