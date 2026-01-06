<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\Identity\Actions\DeleteNotificationAction;
use App\Domains\Identity\Actions\GetUserNotificationsAction;
use App\Domains\Identity\Actions\MarkNotificationsReadAction;
use App\Domains\Identity\Models\Notification;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Resources\NotificationResource;
use App\Domains\Shared\Enums\NotificationType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class NotificationController extends Controller
{
    public function __construct(
        private readonly GetUserNotificationsAction $getNotifications,
        private readonly MarkNotificationsReadAction $markRead,
        private readonly DeleteNotificationAction $deleteNotification,
    ) {}

    /**
     * List user's notifications.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'unread_only' => ['nullable', 'boolean'],
            'type' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $type = isset($validated['type']) ? NotificationType::tryFrom($validated['type']) : null;

        $notifications = $this->getNotifications->execute(
            user: $user,
            unreadOnly: (bool) ($validated['unread_only'] ?? false),
            type: $type,
            perPage: $validated['per_page'] ?? 20,
        );

        return NotificationResource::collection($notifications);
    }

    /**
     * Get unread count and summary.
     */
    public function summary(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => [
                'unreadCount' => $this->getNotifications->unreadCount($user),
                'countByType' => $this->getNotifications->countByType($user),
            ],
        ]);
    }

    /**
     * Get a single notification.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $notification = Notification::with(['actor.profile'])
            ->where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        return response()->json([
            'data' => new NotificationResource($notification),
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $notification = Notification::where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->markRead->execute($notification);

        return response()->json([
            'message' => 'Notification marked as read.',
            'data' => new NotificationResource($notification->fresh()),
        ]);
    }

    /**
     * Mark multiple or all notifications as read.
     */
    public function markMultipleAsRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'uuids' => ['nullable', 'array'],
            'uuids.*' => ['string', 'uuid'],
            'all' => ['nullable', 'boolean'],
        ]);

        if (! empty($validated['all'])) {
            $count = $this->markRead->markAllRead($user);
            $message = "All notifications marked as read ({$count}).";
        } elseif (! empty($validated['uuids'])) {
            $count = $this->markRead->markMultiple($user, $validated['uuids']);
            $message = "{$count} notification(s) marked as read.";
        } else {
            return response()->json([
                'message' => 'Please provide uuids array or set all=true.',
            ], 422);
        }

        return response()->json([
            'message' => $message,
            'data' => [
                'markedCount' => $count,
                'unreadCount' => $this->getNotifications->unreadCount($user),
            ],
        ]);
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $notification = Notification::where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->deleteNotification->execute($notification);

        return response()->json([
            'message' => 'Notification deleted.',
        ]);
    }

    /**
     * Delete multiple notifications or all read notifications.
     */
    public function destroyMultiple(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'uuids' => ['nullable', 'array'],
            'uuids.*' => ['string', 'uuid'],
            'all_read' => ['nullable', 'boolean'],
        ]);

        if (! empty($validated['all_read'])) {
            $count = $this->deleteNotification->deleteAllRead($user);
            $message = "All read notifications deleted ({$count}).";
        } elseif (! empty($validated['uuids'])) {
            $count = $this->deleteNotification->deleteMultiple($user, $validated['uuids']);
            $message = "{$count} notification(s) deleted.";
        } else {
            return response()->json([
                'message' => 'Please provide uuids array or set all_read=true.',
            ], 422);
        }

        return response()->json([
            'message' => $message,
            'data' => [
                'deletedCount' => $count,
            ],
        ]);
    }
}
