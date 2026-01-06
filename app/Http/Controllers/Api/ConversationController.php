<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\Duel\Actions\GetUserConversationsAction;
use App\Domains\Duel\Actions\SendMessageAction;
use App\Domains\Duel\Actions\StartConversationAction;
use App\Domains\Duel\Models\ChatRoom;
use App\Domains\Duel\Models\Conversation;
use App\Domains\Duel\Resources\ConversationResource;
use App\Domains\Duel\Resources\MessageResource;
use App\Domains\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Handle direct message conversations between users.
 */
final class ConversationController extends Controller
{
    /**
     * List all conversations for the authenticated user.
     */
    public function index(
        Request $request,
        GetUserConversationsAction $action,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();

        $conversations = $action->execute($user);

        return ConversationResource::collection($conversations);
    }

    /**
     * Start or retrieve a conversation with another user.
     */
    public function store(
        Request $request,
        StartConversationAction $action,
    ): JsonResponse {
        $validated = $request->validate([
            'recipient_uuid' => ['required', 'string', 'exists:users,uuid'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $recipient = User::where('uuid', $validated['recipient_uuid'])->firstOrFail();

        // Can't message yourself
        if ($recipient->id === $user->id) {
            return response()->json([
                'message' => 'Cannot start a conversation with yourself.',
            ], 422);
        }

        $room = $action->execute($user, $recipient);

        // Get the conversation for the current user
        $conversation = $room->conversations()->where('user_id', $user->id)->first();

        return response()->json([
            'data' => new ConversationResource($conversation),
            'message' => 'Conversation started.',
        ], 201);
    }

    /**
     * Get a specific conversation.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var ChatRoom|null $room */
        $room = ChatRoom::where('uuid', $uuid)->first();

        if ($room === null) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        // Check user is participant
        if (! $room->hasParticipant($user)) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        $conversation = $room->conversations()->where('user_id', $user->id)->first();

        if ($conversation === null) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        return response()->json([
            'data' => new ConversationResource($conversation->load('chatRoom.conversations.user.profile')),
        ]);
    }

    /**
     * Get messages in a conversation.
     */
    public function messages(Request $request, string $uuid): AnonymousResourceCollection|JsonResponse
    {
        $validated = $request->validate([
            'before' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        /** @var User $user */
        $user = $request->user();

        /** @var ChatRoom|null $room */
        $room = ChatRoom::where('uuid', $uuid)->first();

        if ($room === null) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        // Check user is participant
        if (! $room->hasParticipant($user)) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        $query = $room->messages()
            ->with(['user.profile'])
            ->orderByDesc('created_at');

        if (isset($validated['before'])) {
            $query->where('created_at', '<', $validated['before']);
        }

        $perPage = $validated['per_page'] ?? 50;

        // Mark as read
        $conversation = $room->conversations()->where('user_id', $user->id)->first();
        $conversation?->markAsRead();

        return MessageResource::collection($query->paginate($perPage));
    }

    /**
     * Send a message in a conversation.
     */
    public function sendMessage(
        Request $request,
        string $uuid,
        SendMessageAction $action,
    ): JsonResponse {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
        ]);

        /** @var User $user */
        $user = $request->user();

        /** @var ChatRoom|null $room */
        $room = ChatRoom::where('uuid', $uuid)->first();

        if ($room === null) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        // Check user is participant
        if (! $room->hasParticipant($user)) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        // Check if blocked
        $conversation = $room->conversations()->where('user_id', $user->id)->first();
        if ($conversation?->is_blocked) {
            return response()->json(['message' => 'This conversation is blocked.'], 403);
        }

        // Check if the other participant has blocked
        $otherConversation = $room->conversations()->where('user_id', '!=', $user->id)->first();
        if ($otherConversation?->is_blocked) {
            return response()->json(['message' => 'Cannot send message to this user.'], 403);
        }

        $message = $action->execute(
            room: $room,
            content: $validated['content'],
            user: $user,
        );

        return response()->json([
            'data' => new MessageResource($message->load('user.profile')),
            'message' => 'Message sent.',
        ], 201);
    }

    /**
     * Mark conversation as read.
     */
    public function markAsRead(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var ChatRoom|null $room */
        $room = ChatRoom::where('uuid', $uuid)->first();

        if ($room === null) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $conversation = $room->conversations()->where('user_id', $user->id)->first();

        if ($conversation === null) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $conversation->markAsRead();

        return response()->json(['message' => 'Marked as read.']);
    }

    /**
     * Mute/unmute a conversation.
     */
    public function toggleMute(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var ChatRoom|null $room */
        $room = ChatRoom::where('uuid', $uuid)->first();

        if ($room === null) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $conversation = $room->conversations()->where('user_id', $user->id)->first();

        if ($conversation === null) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $conversation->toggleMute();

        return response()->json([
            'message' => $conversation->is_muted ? 'Conversation muted.' : 'Conversation unmuted.',
            'is_muted' => $conversation->is_muted,
        ]);
    }

    /**
     * Block/unblock a conversation.
     */
    public function block(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'blocked' => ['required', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        /** @var ChatRoom|null $room */
        $room = ChatRoom::where('uuid', $uuid)->first();

        if ($room === null) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $conversation = $room->conversations()->where('user_id', $user->id)->first();

        if ($conversation === null) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $conversation->setBlocked($validated['blocked']);

        return response()->json([
            'message' => $validated['blocked'] ? 'Conversation blocked.' : 'Conversation unblocked.',
            'is_blocked' => $conversation->is_blocked,
        ]);
    }
}
