<?php

namespace App\Services;

use App\Events\NewNotificationEvent;
use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /**
     * Create a notification for a user and broadcast it via WebSocket.
     */
    public function create(User $recipient, string $type, string $title, string $message, array $data = []): Notification
    {
        $notification = Notification::create([
            'user_id'  => $recipient->id,
            'type'     => $type,
            'title'    => $title,
            'body'     => $message,
            'data'     => $data,
        ]);

        broadcast(new NewNotificationEvent($recipient, $notification))->toOthers();

        return $notification;
    }
}
