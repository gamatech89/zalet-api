<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateConversationRequest;
use App\Models\Conversation;
use App\Services\PlanLimitsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    /**
     * List user's conversations.
     * GET /api/v1/conversations
     */
    public function index(Request $request): JsonResponse
    {
        $conversations = $request->user()
            ->conversations()
            ->with(['latestMessage.sender:id,username', 'users:id,username'])
            ->withCount('messages')
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $conversations->map(function ($conversation) use ($request) {
                return [
                    'id' => $conversation->id,
                    'name' => $conversation->name ?? $this->getConversationName($conversation, $request->user()),
                    'is_group' => $conversation->is_group,
                    'participants' => $conversation->users->map(fn ($u) => [
                        'id' => $u->id,
                        'username' => $u->username,
                    ]),
                    'last_message' => $conversation->latestMessage ? [
                        'content' => $conversation->latestMessage->content,
                        'sender' => $conversation->latestMessage->sender->username,
                        'sent_at' => $conversation->latestMessage->created_at->toIso8601String(),
                    ] : null,
                    'messages_count' => $conversation->messages_count,
                    'updated_at' => $conversation->updated_at->toIso8601String(),
                ];
            }),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    /**
     * Create a new conversation (DM or group).
     * POST /api/v1/conversations
     */
    public function store(CreateConversationRequest $request): JsonResponse
    {
        $user = $request->user();
        $userIds = collect($request->user_ids)->unique()->values();
        $isGroup = $request->boolean('is_group', false);

        // For DMs, check if conversation already exists
        if (!$isGroup && $userIds->count() === 1) {
            $existingConversation = $user->conversations()
                ->where('is_group', false)
                ->whereHas('users', fn ($q) => $q->where('users.id', $userIds->first()))
                ->first();

            if ($existingConversation) {
                return response()->json([
                    'message' => 'Conversation already exists.',
                    'data' => ['id' => $existingConversation->id],
                ], 200);
            }
        }

        // Groups require a name
        if ($isGroup && !$request->name) {
            return response()->json([
                'message' => 'Group conversations require a name.',
            ], 422);
        }

        // ── Plan limit check for groups ──
        if ($isGroup) {
            $planLimitsService = app(PlanLimitsService::class);
            $canJoin = $planLimitsService->canJoinGroup($user);
            if ($canJoin !== true) {
                return response()->json([
                    'message' => $canJoin,
                    'error_type' => 'plan_limit',
                ], 403);
            }
        }

        $conversation = Conversation::create([
            'name' => $request->name,
            'is_group' => $isGroup,
        ]);

        // Add all participants including current user
        $allUserIds = $userIds->push($user->id)->unique();
        $conversation->users()->attach($allUserIds, [
            'joined_at' => now(),
        ]);

        return response()->json([
            'message' => 'Conversation created successfully.',
            'data' => [
                'id' => $conversation->id,
                'name' => $conversation->name,
                'is_group' => $conversation->is_group,
                'participants' => $conversation->users->map(fn ($u) => [
                    'id' => $u->id,
                    'username' => $u->username,
                ]),
            ],
        ], 201);
    }

    /**
     * Get conversation details.
     * GET /api/v1/conversations/{conversation}
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $conversation->load(['users:id,username']);

        return response()->json([
            'data' => [
                'id' => $conversation->id,
                'name' => $conversation->name ?? $this->getConversationName($conversation, $request->user()),
                'is_group' => $conversation->is_group,
                'participants' => $conversation->users->map(fn ($u) => [
                    'id' => $u->id,
                    'username' => $u->username,
                ]),
                'created_at' => $conversation->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get the count of unread conversations.
     * GET /api/v1/conversations/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $count = $user->conversations()
            ->where(function ($query) use ($user) {
                $query->whereHas('messages', function ($mQuery) use ($user) {
                    $mQuery->where('sender_id', '!=', $user->id)
                        ->where(function ($subQuery) {
                            $subQuery->whereRaw('messages.created_at > conversation_user.last_read_at')
                                ->orWhereNull('conversation_user.last_read_at');
                        });
                });
            })
            ->count();

        return response()->json([
            'unread_count' => $count,
        ]);
    }

    /**
     * Generate a display name for DM conversations (other user's name).
     */
    private function getConversationName(Conversation $conversation, $currentUser): string
    {
        if ($conversation->is_group) {
            return $conversation->name ?? 'Group Chat';
        }

        $otherUser = $conversation->users->firstWhere('id', '!=', $currentUser->id);
        return $otherUser?->username ?? 'Unknown';
    }
}
