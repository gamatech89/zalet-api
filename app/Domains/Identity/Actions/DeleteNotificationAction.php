<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\Notification;
use App\Domains\Identity\Models\User;

/**
 * Delete notifications.
 */
final class DeleteNotificationAction
{
    /**
     * Delete a single notification.
     */
    public function execute(Notification $notification): bool
    {
        return (bool) $notification->delete();
    }

    /**
     * Delete multiple notifications by UUIDs.
     *
     * @param array<string> $uuids
     */
    public function deleteMultiple(User $user, array $uuids): int
    {
        return $user->appNotifications()
            ->whereIn('uuid', $uuids)
            ->delete();
    }

    /**
     * Delete all read notifications for a user.
     */
    public function deleteAllRead(User $user): int
    {
        return $user->appNotifications()
            ->whereNotNull('read_at')
            ->delete();
    }

    /**
     * Delete notifications older than a certain date.
     */
    public function deleteOlderThan(User $user, \DateTimeInterface $date): int
    {
        return $user->appNotifications()
            ->where('created_at', '<', $date)
            ->delete();
    }
}
