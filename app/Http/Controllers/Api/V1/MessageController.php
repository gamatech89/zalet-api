<?php

namespace App\Http\Controllers\Api\V1;

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

        $messages = $conversation->messages()
            ->with(['sender:id,username', 'sender.profile:user_id,avatar_url', 'reactions.user:id,username', 'repliedTo.sender:id,username'])
            ->when($blockedIds->isNotEmpty(), fn($q) => $q->whereNotIn('sender_id', $blockedIds))
            ->orderBy('created_at', 'desc')
            ->paginate(50);

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
            }
            else {
                $data['message_type'] = 'file';
            }
        }

        // Handle reply
        if ($request->reply_to_id) {
            $data['reply_to_id'] = $request->reply_to_id;
        }

        $message = $conversation->messages()->create($data);
        $message->load(['sender:id,username', 'sender.profile:user_id,avatar_url', 'reactions', 'repliedTo.sender:id,username']);

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
            ->with(['sender:id,username', 'sender.profile:user_id,avatar_url', 'reactions.user:id,username', 'repliedTo.sender:id,username'])
            ->where('created_at', '<=', $message->created_at)
            ->where('id', '!=', $message->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $after = $conversation->messages()
            ->with(['sender:id,username', 'sender.profile:user_id,avatar_url', 'reactions.user:id,username', 'repliedTo.sender:id,username'])
            ->where('created_at', '>', $message->created_at)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        $message->load(['sender:id,username', 'reactions.user:id,username', 'repliedTo.sender:id,username']);

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
            'emoji' => ['required', 'string', 'max:8'],
        ]);

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
                'avatar_url' => $message->sender->profile?->avatar_url,
            ],
            'reactions' => $this->formatReactions($message),
            'reply_to' => $message->repliedTo ? [
                'id' => $message->repliedTo->id,
                'content' => $message->repliedTo->content,
                'message_type' => $message->repliedTo->message_type ?? 'text',
                'sender' => [
                    'id' => $message->repliedTo->sender->id,
                    'username' => $message->repliedTo->sender->username,
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