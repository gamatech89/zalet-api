<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * List notifications for the authenticated user.
     *
     * GET /api/v1/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::forUser($request->user()->id)
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        // Get all unique media IDs from the notifications
        $mediaIds = [];
        foreach ($notifications->items() as $notification) {
            if (isset($notification->data['media_id'])) {
                $mediaIds[] = $notification->data['media_id'];
            }
        }
        $mediaIds = array_unique($mediaIds);

        // Fetch their types
        $mediaTypes = [];
        if (!empty($mediaIds)) {
            $mediaTypes = \App\Models\Media::whereIn('id', $mediaIds)
                ->pluck('type', 'id')
                ->toArray();
        }

        // Map them back to the notification data
        $notifications->getCollection()->transform(function ($notification) use ($mediaTypes) {
            if (isset($notification->data['media_id'])) {
                $mediaId = $notification->data['media_id'];
                $data = $notification->data;
                $data['media_type'] = $mediaTypes[$mediaId] ?? 'moment'; // Fallback to moment
                $notification->data = $data;
            }
            return $notification;
        });

        return response()->json($notifications);
    }

    /**
     * Get the count of unread notifications.
     *
     * GET /api/v1/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::forUser($request->user()->id)
            ->unread()
            ->count();

        return response()->json([
            'unread_count' => $count,
        ]);
    }

    /**
     * Mark a single notification as read.
     *
     * POST /api/v1/notifications/{notification}/read
     */
    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        // Ensure the notification belongs to the authenticated user
        abort_if($notification->user_id !== $request->user()->id, 403);

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read.',
            'data' => $notification->fresh(),
        ]);
    }

    /**
     * Mark all notifications as read.
     *
     * POST /api/v1/notifications/read-all
     */
    public function markAllRead(Request $request): JsonResponse
    {
        Notification::forUser($request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read.',
        ]);
    }
}
