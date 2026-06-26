<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\MessageSentEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateConversationRequest;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\PlanLimitsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ConversationController extends Controller
{
    /**
     * Discover groups: all public groups + groups the user is already in.
     * GET /api/v1/groups
     */
    public function groups(Request $request): JsonResponse
    {
        $user = $request->user();

        $groups = Conversation::where('is_group', true)
            ->where(function ($q) use ($user) {
                // Public groups anyone can discover, plus private groups the user is in
                $q->where('is_public', true)
                    ->orWhereHas('users', fn ($q2) => $q2->where('users.id', $user->id));
            })
            ->withCount('users as member_count')
            ->orderByDesc('updated_at')
            ->paginate(30);

        return response()->json([
            'data' => $groups->map(function ($group) use ($user) {
                $membership = $group->users()->where('users.id', $user->id)->first();
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'is_public' => $group->is_public,
                    'member_count' => $group->member_count,
                    'is_member' => $membership !== null,
                    'my_role' => $membership?->pivot?->role,
                    'invite_code' => $group->is_public ? $group->invite_code : null,
                    'updated_at' => $group->updated_at->toIso8601String(),
                ];
            }),
            'meta' => [
                'current_page' => $groups->currentPage(),
                'last_page' => $groups->lastPage(),
                'per_page' => $groups->perPage(),
                'total' => $groups->total(),
            ],
        ]);
    }

    /**
     * List user's conversations.
     * GET /api/v1/conversations
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 50), 100);
        $conversations = $request->user()
            ->conversations()
            ->with(['latestMessage.sender:id,username', 'users:id,username,name', 'board:id,name,slug,image_url'])
            ->withCount('messages')
            ->orderBy('updated_at', 'desc')
            ->paginate($perPage);

        $userId = $request->user()->id;

        return response()->json([
            'data' => $conversations->map(function ($conversation) use ($request, $userId) {
                $myUser = $conversation->users->firstWhere('id', $userId);
                $lastReadAt = $myUser?->pivot?->last_read_at;

                $unreadCount = $conversation->messages()
                    ->where('sender_id', '!=', $userId)
                    ->when($lastReadAt, fn ($q) => $q->where('created_at', '>', $lastReadAt))
                    ->limit(99)
                    ->count();

                return [
                    'id' => $conversation->id,
                    'name' => $conversation->name ?? $this->getConversationName($conversation, $request->user()),
                    'is_group' => $conversation->is_group,
                    'is_public' => $conversation->is_public,
                    'my_role' => $myUser?->pivot?->role,
                    'last_read_at' => $lastReadAt ? now()->parse($lastReadAt)->toIso8601String() : null,
                    'unread_count' => $unreadCount,
                    'participants' => $conversation->users->map(fn ($u) => [
                        'id' => $u->id,
                        'username' => $u->username,
                        'name' => $u->name,
                    ]),
                    'last_message' => $conversation->latestMessage ? [
                        'content' => $conversation->latestMessage->content,
                        'message_type' => $conversation->latestMessage->message_type,
                        'sender' => $conversation->latestMessage->sender->username,
                        'sent_at' => $conversation->latestMessage->created_at->toIso8601String(),
                    ] : null,
                    'messages_count' => $conversation->messages_count,
                    'updated_at' => $conversation->updated_at->toIso8601String(),
                    'board_slug' => $conversation->board?->slug,
                    'board_name' => $conversation->board?->name,
                    'board_image_url' => $conversation->board?->image_url,
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

        if (!$isGroup && $userIds->count() === 1) {
            $targetUserId = $userIds->first();

            $isBlocked = Block::where(function ($q) use ($user, $targetUserId) {
                $q->where('blocker_id', $user->id)->where('blocked_id', $targetUserId);
            })->orWhere(function ($q) use ($user, $targetUserId) {
                $q->where('blocker_id', $targetUserId)->where('blocked_id', $user->id);
            })->exists();

            if ($isBlocked) {
                return response()->json(['message' => 'Ne možeš slati poruke ovom korisniku.'], 422);
            }

            $existingConversation = $user->conversations()
                ->where('is_group', false)
                ->whereHas('users', fn ($q) => $q->where('users.id', $targetUserId))
                ->first();

            if ($existingConversation) {
                return response()->json([
                    'message' => 'Conversation already exists.',
                    'data' => ['id' => $existingConversation->id],
                ], 200);
            }
        }

        if ($isGroup && !$request->name) {
            return response()->json([
                'message' => 'Group conversations require a name.',
            ], 422);
        }

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

        $isPublic = $isGroup && $request->boolean('is_public', false);
        $inviteCode = $isPublic ? $this->generateInviteCode() : null;

        $conversation = Conversation::create([
            'name' => $request->name,
            'is_group' => $isGroup,
            'is_public' => $isPublic,
            'invite_code' => $inviteCode,
        ]);

        if ($isGroup) {
            // Creator gets owner role; other members get member role
            $pivotData = [$user->id => ['joined_at' => now(), 'role' => 'owner']];
            foreach ($userIds as $uid) {
                if ($uid !== $user->id) {
                    $pivotData[$uid] = ['joined_at' => now(), 'role' => 'member'];
                }
            }
            $conversation->users()->attach($pivotData);
        } else {
            $allUserIds = $userIds->push($user->id)->unique();
            $conversation->users()->attach($allUserIds, ['joined_at' => now()]);
        }

        $conversation->load(['users:id,username']);

        return response()->json([
            'message' => 'Conversation created successfully.',
            'data' => [
                'id' => $conversation->id,
                'name' => $conversation->name,
                'is_group' => $conversation->is_group,
                'is_public' => $conversation->is_public,
                'invite_code' => $conversation->is_public ? $conversation->invite_code : null,
                'my_role' => $isGroup ? 'owner' : null,
                'participants' => $conversation->users->map(fn ($u) => [
                    'id' => $u->id,
                    'username' => $u->username,
                ]),
            ],
        ], 201);
    }

    /**
     * Get conversation details (including roles for groups).
     * GET /api/v1/conversations/{conversation}
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $conversation->load(['users:id,username,name', 'pinnedMessage.sender:id,username', 'bans.user:id,username,name']);

        $myUser = $conversation->users->firstWhere('id', $request->user()->id);

        return response()->json([
            'data' => [
                'id' => $conversation->id,
                'name' => $conversation->name ?? $this->getConversationName($conversation, $request->user()),
                'is_group' => $conversation->is_group,
                'is_public' => $conversation->is_public,
                'invite_code' => $conversation->is_public ? $conversation->invite_code : null,
                'my_role' => $myUser?->pivot?->role,
                'last_read_at' => ($lr = $myUser?->pivot?->last_read_at) ? now()->parse($lr)->toIso8601String() : null,
                'participants' => $conversation->users->map(fn ($u) => [
                    'id' => $u->id,
                    'username' => $u->username,
                    'name' => $u->name,
                    'role' => $u->pivot?->role,
                ]),
                'pinned_message' => $conversation->pinnedMessage ? [
                    'id' => $conversation->pinnedMessage->id,
                    'content' => $conversation->pinnedMessage->content,
                    'message_type' => $conversation->pinnedMessage->message_type,
                    'sender' => ['id' => $conversation->pinnedMessage->sender->id, 'username' => $conversation->pinnedMessage->sender->username],
                ] : null,
                'banned_users' => $conversation->bans
                    ->filter(fn ($ban) => $ban->banned_until === null || $ban->banned_until->isFuture())
                    ->map(fn ($ban) => [
                        'id'          => $ban->user->id,
                        'username'    => $ban->user->username,
                        'name'        => $ban->user->name,
                        'banned_at'   => $ban->banned_at->toIso8601String(),
                        'banned_until' => $ban->banned_until?->toIso8601String(),
                        'reason'      => $ban->reason,
                    ])->values(),
                'created_at' => $conversation->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Mark all messages in a conversation as read.
     * POST /api/v1/conversations/{conversation}/read
     */
    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $conversation->users()->updateExistingPivot($request->user()->id, [
            'last_read_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Update group name or visibility.
     * PATCH /api/v1/conversations/{conversation}
     */
    public function update(Request $request, Conversation $conversation): JsonResponse
    {
        if (!$conversation->is_group) {
            return response()->json(['message' => 'Cannot update a direct conversation.'], 422);
        }

        Gate::authorize('update', $conversation);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        // Auto-generate invite code when making group public for the first time
        if (isset($validated['is_public']) && $validated['is_public'] && !$conversation->invite_code) {
            $validated['invite_code'] = $this->generateInviteCode();
        }

        $conversation->update($validated);

        return response()->json([
            'message' => 'Group updated.',
            'data' => [
                'id' => $conversation->id,
                'name' => $conversation->name,
                'is_public' => $conversation->is_public,
                'invite_code' => $conversation->is_public ? $conversation->invite_code : null,
            ],
        ]);
    }

    /**
     * Add members to a group.
     * POST /api/v1/conversations/{conversation}/members
     */
    public function addMembers(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('addMembers', $conversation);

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['uuid', 'exists:users,id'],
        ]);

        $existingIds = $conversation->users()->pluck('users.id')->toArray();
        $toAdd = collect($validated['user_ids'])->reject(fn ($id) => in_array($id, $existingIds));

        if ($toAdd->isEmpty()) {
            return response()->json(['message' => 'All users are already members.'], 422);
        }

        $pivotData = $toAdd->mapWithKeys(fn ($id) => [$id => ['joined_at' => now(), 'role' => 'member']])->toArray();
        $conversation->users()->attach($pivotData);

        $addedNames = User::whereIn('id', $toAdd->values())->pluck('username')->implode(', ');
        $actor = $this->displayName($request->user());
        $this->postSystemMessage($conversation, $request->user()->id, "{$actor} je dodao {$addedNames} u grupu.");

        return response()->json(['message' => 'Members added.']);
    }

    /**
     * Kick a member from a group.
     * DELETE /api/v1/conversations/{conversation}/members/{member}
     */
    public function kickMember(Request $request, Conversation $conversation, User $member): JsonResponse
    {
        Gate::authorize('kickMember', $conversation);

        if ($member->id === $request->user()->id) {
            return response()->json(['message' => 'Use the leave endpoint to leave the group.'], 422);
        }

        $actorRole = $this->getUserRole($request->user(), $conversation);
        $targetRole = $this->getUserRole($member, $conversation);

        if ($targetRole === null) {
            return response()->json(['message' => 'User is not a member of this group.'], 422);
        }
        if ($targetRole === 'owner') {
            return response()->json(['message' => 'Cannot kick the group owner.'], 403);
        }
        // Admins can only kick regular members
        if ($actorRole === 'admin' && $targetRole !== 'member') {
            return response()->json(['message' => 'Admins can only kick regular members.'], 403);
        }

        $conversation->users()->detach($member->id);

        $actor = $this->displayName($request->user());
        $target = $this->displayName($member);
        $this->postSystemMessage($conversation, $request->user()->id, "{$actor} je uklonio {$target} iz grupe.");

        return response()->json(['message' => 'Member removed from group.']);
    }

    /**
     * Ban a user from a group (also removes them if they are a member).
     * POST /api/v1/conversations/{conversation}/bans
     */
    public function banMember(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('banMember', $conversation);

        $validated = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'reason'  => ['nullable', 'string', 'max:255'],
            'hours'   => ['nullable', 'integer', 'in:1,8,24'],
        ]);

        $member = User::findOrFail($validated['user_id']);

        if ($member->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot ban yourself.'], 422);
        }

        $actorRole = $this->getUserRole($request->user(), $conversation);
        $targetRole = $this->getUserRole($member, $conversation);

        if ($targetRole === 'owner') {
            return response()->json(['message' => 'Cannot ban the group owner.'], 403);
        }
        if ($actorRole === 'admin' && $targetRole !== 'member') {
            return response()->json(['message' => 'Admins can only ban regular members.'], 403);
        }

        // Remove from group if they are a member
        $conversation->users()->detach($member->id);

        $bannedUntil = isset($validated['hours']) ? now()->addHours($validated['hours']) : null;

        $conversation->bans()->updateOrCreate(
            ['user_id' => $member->id],
            [
                'banned_by'    => $request->user()->id,
                'reason'       => $validated['reason'] ?? null,
                'banned_until' => $bannedUntil,
                'banned_at'    => now(),
            ]
        );

        $actor = $this->displayName($request->user());
        $target = $this->displayName($member);
        $this->postSystemMessage($conversation, $request->user()->id, "{$actor} je banovao {$target}.");

        return response()->json(['message' => 'User banned from group.']);
    }

    /**
     * Remove a ban from a user.
     * DELETE /api/v1/conversations/{conversation}/bans/{member}
     */
    public function unbanMember(Request $request, Conversation $conversation, User $member): JsonResponse
    {
        Gate::authorize('banMember', $conversation);

        $deleted = $conversation->bans()->where('user_id', $member->id)->delete();

        if (!$deleted) {
            return response()->json(['message' => 'User is not banned.'], 404);
        }

        $actor = $this->displayName($request->user());
        $target = $this->displayName($member);
        $this->postSystemMessage($conversation, $request->user()->id, "{$actor} je uklonio ban za {$target}.");

        return response()->json(['message' => 'User unbanned.']);
    }

    /**
     * Promote or demote a member's role (owner only).
     * PATCH /api/v1/conversations/{conversation}/members/{member}/role
     */
    public function updateMemberRole(Request $request, Conversation $conversation, User $member): JsonResponse
    {
        Gate::authorize('updateMemberRole', $conversation);

        $validated = $request->validate([
            'role' => ['required', 'in:admin,member'],
        ]);

        if ($member->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot change your own role.'], 422);
        }

        $targetRole = $this->getUserRole($member, $conversation);

        if ($targetRole === null) {
            return response()->json(['message' => 'User is not a member of this group.'], 422);
        }
        if ($targetRole === 'owner') {
            return response()->json(['message' => "Cannot change the owner's role."], 422);
        }

        $conversation->users()->updateExistingPivot($member->id, ['role' => $validated['role']]);

        $actor = $this->displayName($request->user());
        $target = $this->displayName($member);
        $text = $validated['role'] === 'admin'
            ? "{$actor} je postavio {$target} za admina."
            : "{$actor} je uklonio {$target} iz uloge admina.";
        $this->postSystemMessage($conversation, $request->user()->id, $text);

        return response()->json(['message' => 'Member role updated.']);
    }

    /**
     * Leave a group conversation.
     * DELETE /api/v1/conversations/{conversation}/leave
     */
    public function leave(Request $request, Conversation $conversation): JsonResponse
    {
        if (!$conversation->is_group) {
            return response()->json(['message' => 'Cannot leave a direct conversation.'], 422);
        }

        $user = $request->user();

        if (!$conversation->users()->where('users.id', $user->id)->exists()) {
            return response()->json(['message' => 'You are not a member of this group.'], 422);
        }

        $memberCount = $conversation->users()->count();

        if ($memberCount === 1) {
            $conversation->delete();
            return response()->json(['message' => 'Group deleted as you were the last member.']);
        }

        $userRole = $this->getUserRole($user, $conversation);

        if ($userRole === 'owner') {
            // Auto-promote the oldest admin, or failing that, the oldest member
            $nextOwner = $conversation->users()
                ->where('users.id', '!=', $user->id)
                ->wherePivot('role', 'admin')
                ->orderByPivot('joined_at', 'asc')
                ->first();

            if (!$nextOwner) {
                $nextOwner = $conversation->users()
                    ->where('users.id', '!=', $user->id)
                    ->orderByPivot('joined_at', 'asc')
                    ->first();
            }

            if ($nextOwner) {
                $conversation->users()->updateExistingPivot($nextOwner->id, ['role' => 'owner']);
            }
        }

        $conversation->users()->detach($user->id);

        $name = $this->displayName($user);
        $this->postSystemMessage($conversation, $user->id, "{$name} je napustio grupu.");

        return response()->json(['message' => 'You have left the group.']);
    }

    /**
     * Get public group info by invite code without joining.
     * GET /api/v1/conversations/invite/{inviteCode}
     */
    public function groupInfo(Request $request, string $inviteCode): JsonResponse
    {
        $conversation = Conversation::where('invite_code', $inviteCode)
            ->where('is_public', true)
            ->where('is_group', true)
            ->withCount('users')
            ->first();

        if (!$conversation) {
            return response()->json(['message' => 'Invalid or expired invite link.'], 404);
        }

        $isMember = $conversation->users()->where('users.id', $request->user()->id)->exists();

        return response()->json([
            'data' => [
                'id' => $conversation->id,
                'name' => $conversation->name,
                'description' => $conversation->description ?? null,
                'member_count' => $conversation->users_count,
                'invite_code' => $conversation->invite_code,
                'is_member' => $isMember,
            ],
        ]);
    }

    /**
     * Join a public group via invite code.
     * GET /api/v1/conversations/join/{inviteCode}
     */
    public function joinByCode(Request $request, string $inviteCode): JsonResponse
    {
        $conversation = Conversation::where('invite_code', $inviteCode)
            ->where('is_public', true)
            ->where('is_group', true)
            ->first();

        if (!$conversation) {
            return response()->json(['message' => 'Invalid or expired invite link.'], 404);
        }

        $user = $request->user();

        if ($conversation->bans()->where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNull('banned_until')->orWhere('banned_until', '>', now()))
            ->exists()) {
            return response()->json(['message' => 'You are banned from this group.'], 403);
        }

        if ($conversation->users()->where('users.id', $user->id)->exists()) {
            return response()->json([
                'message' => 'You are already a member.',
                'data' => ['id' => $conversation->id],
            ]);
        }

        $planLimitsService = app(PlanLimitsService::class);
        $canJoin = $planLimitsService->canJoinGroup($user);
        if ($canJoin !== true) {
            return response()->json([
                'message' => $canJoin,
                'error_type' => 'plan_limit',
            ], 403);
        }

        $conversation->users()->attach($user->id, ['joined_at' => now(), 'role' => 'member']);

        return response()->json([
            'message' => 'Joined group successfully.',
            'data' => ['id' => $conversation->id, 'name' => $conversation->name],
        ], 201);
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

    private function getConversationName(Conversation $conversation, $currentUser): string
    {
        if ($conversation->is_group) {
            return $conversation->name ?? 'Group Chat';
        }

        $otherUser = $conversation->users->firstWhere('id', '!=', $currentUser->id);
        return $otherUser?->name ?? $otherUser?->username ?? 'Unknown';
    }

    private function getUserRole(User $user, Conversation $conversation): ?string
    {
        return $conversation->users()
            ->where('users.id', $user->id)
            ->first()?->pivot?->role;
    }

    private function generateInviteCode(): string
    {
        do {
            $code = strtoupper(Str::random(12));
        } while (Conversation::where('invite_code', $code)->exists());

        return $code;
    }

    private function displayName(User $user): string
    {
        return $user->name ?? $user->username;
    }

    private function postSystemMessage(Conversation $conversation, string $actorId, string $content): void
    {
        $message = $conversation->messages()->create([
            'sender_id'    => $actorId,
            'content'      => $content,
            'message_type' => 'system',
        ]);

        $message->load(['sender:id,username,name', 'sender.profile:user_id,avatar_url']);

        $conversation->touch();

        broadcast(new MessageSentEvent($message));
    }

    /**
     * Pin a message in a group conversation (owner/admin only).
     * POST /api/v1/conversations/{conversation}/pin-message
     */
    public function pinMessage(Request $request, Conversation $conversation): JsonResponse
    {
        if (!$conversation->is_group) {
            return response()->json(['message' => 'Only group chats support pinned messages.'], 422);
        }

        Gate::authorize('update', $conversation);

        $validated = $request->validate([
            'message_id' => ['required', 'uuid', 'exists:messages,id'],
        ]);

        $message = $conversation->messages()->findOrFail($validated['message_id']);

        $conversation->update(['pinned_message_id' => $message->id]);

        $actor = $this->displayName($request->user());
        $preview = mb_strimwidth($message->content ?? '[medija]', 0, 60, '...');
        $this->postSystemMessage($conversation, $request->user()->id, "{$actor} je pinao poruku: \"{$preview}\"");

        $message->load('sender:id,username');

        return response()->json([
            'message' => 'Message pinned.',
            'data' => [
                'id' => $message->id,
                'content' => $message->content,
                'message_type' => $message->message_type,
                'sender' => ['id' => $message->sender->id, 'username' => $message->sender->username],
            ],
        ]);
    }

    /**
     * Unpin the current pinned message (owner/admin only).
     * DELETE /api/v1/conversations/{conversation}/pin-message
     */
    public function unpinMessage(Request $request, Conversation $conversation): JsonResponse
    {
        if (!$conversation->is_group) {
            return response()->json(['message' => 'Only group chats support pinned messages.'], 422);
        }

        Gate::authorize('update', $conversation);

        if (!$conversation->pinned_message_id) {
            return response()->json(['message' => 'No pinned message.'], 422);
        }

        $conversation->update(['pinned_message_id' => null]);

        $actor = $this->displayName($request->user());
        $this->postSystemMessage($conversation, $request->user()->id, "{$actor} je uklonio/la pinanu poruku.");

        return response()->json(['message' => 'Message unpinned.']);
    }
}
