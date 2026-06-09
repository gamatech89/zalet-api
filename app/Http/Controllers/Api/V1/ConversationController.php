<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateConversationRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Services\PlanLimitsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

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
                $myUser = $conversation->users->firstWhere('id', $request->user()->id);
                return [
                    'id' => $conversation->id,
                    'name' => $conversation->name ?? $this->getConversationName($conversation, $request->user()),
                    'is_group' => $conversation->is_group,
                    'is_public' => $conversation->is_public,
                    'my_role' => $myUser?->pivot?->role,
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

        $conversation->load(['users']);

        $myUser = $conversation->users->firstWhere('id', $request->user()->id);

        return response()->json([
            'data' => [
                'id' => $conversation->id,
                'name' => $conversation->name ?? $this->getConversationName($conversation, $request->user()),
                'is_group' => $conversation->is_group,
                'is_public' => $conversation->is_public,
                'invite_code' => $conversation->is_public ? $conversation->invite_code : null,
                'my_role' => $myUser?->pivot?->role,
                'participants' => $conversation->users->map(fn ($u) => [
                    'id' => $u->id,
                    'username' => $u->username,
                    'role' => $u->pivot?->role,
                ]),
                'created_at' => $conversation->created_at->toIso8601String(),
            ],
        ]);
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
            'reason' => ['nullable', 'string', 'max:255'],
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

        $conversation->bans()->updateOrCreate(
            ['user_id' => $member->id],
            [
                'banned_by' => $request->user()->id,
                'reason' => $validated['reason'] ?? null,
                'banned_at' => now(),
            ]
        );

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

        return response()->json(['message' => 'You have left the group.']);
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

        if ($conversation->bans()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You are banned from this group.'], 403);
        }

        if ($conversation->users()->where('users.id', $user->id)->exists()) {
            return response()->json([
                'message' => 'You are already a member.',
                'data' => ['id' => $conversation->id],
            ]);
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
        return $otherUser?->username ?? 'Unknown';
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
}
