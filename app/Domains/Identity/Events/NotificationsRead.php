<?php

declare(strict_types=1);

namespace App\Domains\Identity\Events;

use App\Domains\Identity\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when notifications are marked as read.
 * Broadcasts to update the user's notification badge count.
 */
final class NotificationsRead implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  int  $userId  The user whose notifications were marked as read
     * @param  array<string>  $notificationUuids  UUIDs of notifications marked as read (empty for "mark all")
     * @param  int  $unreadCount  The new unread count after marking as read
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $notificationUuids,
        public readonly int $unreadCount,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('notifications.'.$this->userId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'notifications.read';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'notification_uuids' => $this->notificationUuids,
            'unread_count' => $this->unreadCount,
            'all_read' => empty($this->notificationUuids),
        ];
    }
}
