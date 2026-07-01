<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\MessageDeletedEvent;
use App\Events\MessageEditedEvent;
use App\Events\MessageReadEvent;
use App\Events\MessageSentEvent;
use App\Events\UserTypingEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    /**
     * Get messages in a conversation.
     * GET /api/v1/conversations/{conversation}/messages
     */
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $blockedIds = Block::where('blocker_id', $request->user()->id)
            ->pluck('blocked_id');

        $query = $conversation->messages()
            ->with(['sender:id,username,name,subscription_level', 'sender.profile:user_id,avatar_url', 'reactions.user:id,username', 'repliedTo.sender:id,username,name,subscription_level'])
            ->when($blockedIds->isNotEmpty(), fn($q) => $q->whereNotIn('sender_id', $blockedIds));

        if ($request->filled('before_id')) {
            $pivot = Message::find($request->before_id);
            if ($pivot) {
                $query->where('created_at', '<', $pivot->created_at);
            }
        }

        $messages = $query->orderBy('created_at', 'desc')->paginate(50);

        // Update last_read_at and broadcast read receipt to the other participant
        $now = now();
        $conversation->users()->updateExistingPivot($request->user()->id, [
            'last_read_at' => $now,
        ]);
        broadcast(new MessageReadEvent($request->user(), $conversation, $now->toIso8601String()))->toOthers();

        return response()->json([
            'data' => $messages->map(fn($message) => $this->formatMessage($message)),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * Send a message in a conversation.
     * POST /api/v1/conversations/{conversation}/messages
     */
    public function store(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('sendMessage', $conversation);

        // In direct (1-on-1) conversations, block the message if either side has blocked the other
        if (!$conversation->is_group) {
            $otherUser = $conversation->users()->where('users.id', '!=', $request->user()->id)->first();
            if ($otherUser) {
                $isBlocked = Block::where(function ($q) use ($request, $otherUser) {
                    $q->where('blocker_id', $request->user()->id)->where('blocked_id', $otherUser->id);
                })->orWhere(function ($q) use ($request, $otherUser) {
                    $q->where('blocker_id', $otherUser->id)->where('blocked_id', $request->user()->id);
                })->exists();

                if ($isBlocked) {
                    return response()->json(['message' => 'Ne možeš slati poruke ovom korisniku.'], 422);
                }
            }
        }

        $data = [
            'sender_id' => $request->user()->id,
            'content' => $request->content,
            'message_type' => 'text',
        ];

        // Handle file upload
        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $path = $file->store('chat-media/' . $conversation->id, 's3');
            $data['media_url'] = Storage::disk('s3')->url($path);

            // Determine message type from MIME
            $mime = $file->getMimeType();
            if (str_starts_with($mime, 'image/')) {
                $data['message_type'] = 'image';
            } elseif (str_starts_with($mime, 'audio/')) {
                $data['message_type'] = 'audio';
            } else {
                $data['message_type'] = 'file';
            }
        }

        // Handle reply
        if ($request->reply_to_id) {
            $data['reply_to_id'] = $request->reply_to_id;
        }

        $message = $conversation->messages()->create($data);
        $message->load(['sender:id,username,name,subscription_level', 'sender.profile:user_id,avatar_url', 'reactions', 'repliedTo.sender:id,username,name,subscription_level']);

        // Update conversation timestamp
        $conversation->touch();

        // Broadcast message to other participants
        broadcast(new MessageSentEvent($message))->toOthers();

        // Parse @mentions and notify mentioned participants
        if ($request->content && preg_match_all('/@([\w]+)/', $request->content, $matches)) {
            $mentionedUsernames = array_unique($matches[1]);
            $participantIds = $conversation->users()->pluck('users.id')->toArray();
            $senderId = $request->user()->id;

            foreach ($mentionedUsernames as $username) {
                $mentioned = \App\Models\User::where('username', $username)
                    ->whereIn('id', $participantIds)
                    ->where('id', '!=', $senderId)
                    ->first();

                if ($mentioned) {
                    app(NotificationService::class)->create(
                        $mentioned,
                        'mention',
                        '@' . $request->user()->username . ' te je pomenuo/la',
                        \Illuminate\Support\Str::limit($request->content, 80),
                        [
                            'conversation_id' => $conversation->id,
                            'message_id'      => $message->id,
                            'sender_username' => $request->user()->username,
                        ]
                    );
                }
            }
        }

        return response()->json([
            'message' => 'Message sent successfully.',
            'data' => $this->formatMessage($message),
        ], 201);
    }

    /**
     * Edit a message (sender only, text messages only).
     * PATCH /api/v1/conversations/{conversation}/messages/{message}
     */
    public function update(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        Gate::authorize('view', $conversation);

        if ($message->conversation_id !== $conversation->id) {
            return response()->json(['message' => 'Message not found in this conversation.'], 404);
        }
        if ($message->sender_id !== $request->user()->id) {
            return response()->json(['message' => 'You can only edit your own messages.'], 403);
        }
        if ($message->message_type !== 'text') {
            return response()->json(['message' => 'Only text messages can be edited.'], 422);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $message->update([
            'content' => $validated['content'],
            'edited_at' => now(),
        ]);

        broadcast(new MessageEditedEvent($message))->toOthers();

        return response()->json([
            'message' => 'Message updated.',
            'data' => $this->formatMessage($message),
        ]);
    }

    /**
     * Get messages around a specific message (for mention deep-linking).
     * GET /api/v1/conversations/{conversation}/messages/around/{message}
     */
    public function around(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        Gate::authorize('view', $conversation);

        if ($message->conversation_id !== $conversation->id) {
            return response()->json(['message' => 'Message not found in this conversation.'], 404);
        }

        $limit = 25;

        $before = $conversation->messages()
            ->with(['sender:id,username,name,subscription_level', 'sender.profile:user_id,avatar_url', 'reactions.user:id,username', 'repliedTo.sender:id,username,name,subscription_level'])
            ->where('created_at', '<=', $message->created_at)
            ->where('id', '!=', $message->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $after = $conversation->messages()
            ->with(['sender:id,username,name,subscription_level', 'sender.profile:user_id,avatar_url', 'reactions.user:id,username', 'repliedTo.sender:id,username,name,subscription_level'])
            ->where('created_at', '>', $message->created_at)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        $message->load(['sender:id,username,name,subscription_level', 'reactions.user:id,username', 'repliedTo.sender:id,username,name,subscription_level']);

        $all = $before->concat([$message])->concat($after);

        return response()->json([
            'data' => $all->map(fn($m) => $this->formatMessage($m)),
            'target_message_id' => $message->id,
            'has_before' => $before->count() === $limit,
            'has_after' => $after->count() === $limit,
        ]);
    }

    /**
     * Add a reaction to a message.
     * POST /api/v1/conversations/{conversation}/messages/{message}/reactions
     */
    public function addReaction(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $request->validate([
            'emoji' => ['required', 'string', 'max:20', 'not_regex:/^[\x00-\x7F]+$/'],
        ]);

        // Max 10 distinct emoji reactions per user per message
        $userReactionCount = MessageReaction::where('message_id', $message->id)
            ->where('user_id', $request->user()->id)
            ->count();
        if ($userReactionCount >= 10) {
            return response()->json(['message' => 'Reaction limit reached.'], 422);
        }

        // Check message belongs to this conversation
        if ($message->conversation_id !== $conversation->id) {
            return response()->json(['message' => 'Message not found in this conversation.'], 404);
        }

        // Toggle reaction — if it exists, remove it; otherwise add it
        $existing = MessageReaction::where([
            'message_id' => $message->id,
            'user_id' => $request->user()->id,
            'emoji' => $request->emoji,
        ])->first();

        if ($existing) {
            $existing->delete();
            $action = 'removed';
        }
        else {
            MessageReaction::create([
                'message_id' => $message->id,
                'user_id' => $request->user()->id,
                'emoji' => $request->emoji,
            ]);
            $action = 'added';
        }

        // Reload reactions for the message
        $message->load('reactions.user:id,username');

        return response()->json([
            'message' => "Reaction {$action}.",
            'data' => [
                'reactions' => $this->formatReactions($message),
            ],
        ]);
    }

    /**
     * Delete a single message (sender, group admin/owner, or platform admin).
     * DELETE /api/v1/conversations/{conversation}/messages/{message}
     */
    public function destroy(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        Gate::authorize('deleteMessage', [$conversation, $message]);

        if ($message->conversation_id !== $conversation->id) {
            return response()->json(['message' => 'Message not found in this conversation.'], 404);
        }

        $messageId = $message->id;
        $message->delete();

        broadcast(new MessageDeletedEvent($messageId, $conversation->id))->toOthers();

        return response()->json(['message' => 'Message deleted.']);
    }

    /**
     * Delete all messages from a member in this group (group admin/owner or platform admin).
     * DELETE /api/v1/conversations/{conversation}/members/{member}/messages
     */
    public function destroyUserMessages(Request $request, Conversation $conversation, \App\Models\User $member): JsonResponse
    {
        Gate::authorize('deleteUserMessages', $conversation);

        $messageIds = $conversation->messages()
            ->where('sender_id', $member->id)
            ->pluck('id');

        $conversation->messages()
            ->where('sender_id', $member->id)
            ->delete();

        foreach ($messageIds as $msgId) {
            broadcast(new MessageDeletedEvent($msgId, $conversation->id))->toOthers();
        }

        return response()->json(['message' => "Deleted {$messageIds->count()} messages."]);
    }

    /**
     * Clear all messages in a conversation (group owner only).
     * DELETE /api/v1/conversations/{conversation}/messages
     */
    public function clearAll(Request $request, Conversation $conversation): JsonResponse
    {
        if (!$conversation->is_group) {
            return response()->json(['message' => 'Only group chats can be cleared.'], 422);
        }

        $myRole = $conversation->users()
            ->where('users.id', $request->user()->id)
            ->first()?->pivot?->role;

        if ($myRole !== 'owner') {
            return response()->json(['message' => 'Only the group owner can clear all messages.'], 403);
        }

        // Delete R2 files for messages with media
        $mediaMessages = $conversation->messages()
            ->whereNotNull('media_url')
            ->pluck('media_url');

        $publicPrefix = config('filesystems.disks.s3.url', '');
        foreach ($mediaMessages as $url) {
            if ($publicPrefix && str_starts_with($url, $publicPrefix)) {
                $path = ltrim(substr($url, strlen($publicPrefix)), '/');
                try {
                    Storage::disk('s3')->delete($path);
                } catch (\Throwable) { /* skip on error */ }
            }
        }

        $conversation->messages()->delete();
        $conversation->update(['pinned_message_id' => null]);

        return response()->json(['message' => 'Chat cleared.']);
    }

    /**
     * Broadcast typing indicator.
     * POST /api/v1/conversations/{conversation}/typing
     */
    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        broadcast(new UserTypingEvent($request->user(), $conversation))->toOthers();

        return response()->json([
            'message' => 'Typing indicator sent.',
        ]);
    }

    /**
     * Format a message for JSON response.
     */
    private function formatMessage(Message $message): array
    {
        return [
            'id' => $message->id,
            'content' => $message->content,
            'message_type' => $message->message_type ?? 'text',
            'media_url' => $message->media_url,
            'sender' => [
                'id' => $message->sender->id,
                'username' => $message->sender->username,
                'name' => $message->sender->name ?: null,
                'avatar_url' => $message->sender->profile?->avatar_url,
                'subscription_level' => $message->sender->subscription_level,
            ],
            'reactions' => $this->formatReactions($message),
            'reply_to' => $message->repliedTo ? [
                'id' => $message->repliedTo->id,
                'content' => $message->repliedTo->content,
                'message_type' => $message->repliedTo->message_type ?? 'text',
                'sender' => [
                    'id' => $message->repliedTo->sender->id,
                    'username' => $message->repliedTo->sender->username,
                    'name' => $message->repliedTo->sender->name ?: null,
                ],
            ] : null,
            'created_at' => $message->created_at->toIso8601String(),
            'edited_at' => $message->edited_at?->toIso8601String(),
        ];
    }

    /**
     * Format reactions grouped by emoji with user info.
     */
    private function formatReactions(Message $message): array
    {
        if (!$message->relationLoaded('reactions') || $message->reactions->isEmpty()) {
            return [];
        }

        return $message->reactions
            ->groupBy('emoji')
            ->map(function ($group, $emoji) {
            return [
                'emoji' => $emoji,
                'count' => $group->count(),
                'users' => $group->map(fn($r) => [
            'id' => $r->user->id,
            'username' => $r->user->username,
            ])->values()->all(),
            ];
        })
            ->values()
            ->all();
    }
}