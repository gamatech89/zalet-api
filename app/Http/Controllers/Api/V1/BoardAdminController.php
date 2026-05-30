<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\BoardCategory;
use App\Models\BoardJoinRequest;
use App\Models\BoardMember;
use App\Models\BoardPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BoardAdminController extends Controller
{
    // =============================================
    // CATEGORIES
    // =============================================

    /**
     * List categories for a board (global + board-specific).
     *
     * GET /api/v1/boards/{board}/categories
     */
    public function listCategories(Board $board): JsonResponse
    {
        $categories = BoardCategory::forBoard($board->id)
            ->active()
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $categories]);
    }

    /**
     * Create a new category for a board.
     *
     * POST /api/v1/boards/{board}/categories
     */
    public function createCategory(Request $request, Board $board): JsonResponse
    {
        $this->authorizeAdmin($request, $board);

        $request->validate([
            'name_en' => 'required|string|max:50',
            'name_sr' => 'required|string|max:50',
            'icon' => 'nullable|string|max:50',
        ]);

        $slug = Str::slug($request->input('name_en'));

        // Ensure unique slug
        $existingSlug = BoardCategory::where('slug', $slug)->exists();
        if ($existingSlug) {
            $slug .= '-' . Str::random(4);
        }

        $category = BoardCategory::create([
            'slug' => $slug,
            'name_en' => $request->input('name_en'),
            'name_sr' => $request->input('name_sr'),
            'icon' => $request->input('icon'),
            'sort_order' => BoardCategory::forBoard($board->id)->max('sort_order') + 1,
            'board_id' => $board->id,
        ]);

        return response()->json([
            'message' => 'Category created.',
            'data' => $category,
        ], 201);
    }

    /**
     * Update a category.
     *
     * PATCH /api/v1/boards/{board}/categories/{category}
     */
    public function updateCategory(Request $request, Board $board, BoardCategory $category): JsonResponse
    {
        $this->authorizeAdmin($request, $board);

        $request->validate([
            'name_en' => 'sometimes|string|max:50',
            'name_sr' => 'sometimes|string|max:50',
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $category->update($request->only(['name_en', 'name_sr', 'icon', 'sort_order']));

        return response()->json([
            'message' => 'Category updated.',
            'data' => $category,
        ]);
    }

    /**
     * Delete a category. Posts reassigned to "general".
     *
     * DELETE /api/v1/boards/{board}/categories/{category}
     */
    public function deleteCategory(Request $request, Board $board, BoardCategory $category): JsonResponse
    {
        $this->authorizeAdmin($request, $board);

        // Don't allow deleting the "general" category
        if ($category->slug === 'general') {
            return response()->json(['message' => 'Cannot delete the general category.'], 422);
        }

        // Reassign posts to "general"
        BoardPost::where('board_id', $board->id)
            ->where('category', $category->slug)
            ->update(['category' => 'general']);

        $category->delete();

        return response()->json(['message' => 'Category deleted. Posts moved to general.']);
    }

    // =============================================
    // MEMBERS
    // =============================================

    /**
     * List board members.
     *
     * GET /api/v1/boards/{board}/members
     */
    public function listMembers(Board $board): JsonResponse
    {
        $members = $board->members()
            ->with('user:id,username,role')
            ->orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'moderator' THEN 1 ELSE 2 END")
            ->get();

        return response()->json(['data' => $members]);
    }

    /**
     * Add a member to the board.
     *
     * POST /api/v1/boards/{board}/members
     */
    public function addMember(Request $request, Board $board): JsonResponse
    {
        $this->authorizeAdmin($request, $board);

        $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'role' => 'required|in:admin,moderator,member',
        ]);

        $exists = $board->members()->where('user_id', $request->input('user_id'))->exists();
        if ($exists) {
            return response()->json(['message' => 'User is already a member.'], 422);
        }

        $member = $board->members()->create([
            'user_id' => $request->input('user_id'),
            'role' => $request->input('role'),
        ]);
        $member->load('user:id,username,role');

        return response()->json([
            'message' => 'Member added.',
            'data' => $member,
        ], 201);
    }

    /**
     * Update a member's role.
     *
     * PATCH /api/v1/boards/{board}/members/{user}
     */
    public function updateMember(Request $request, Board $board, string $userId): JsonResponse
    {
        $this->authorizeAdmin($request, $board);

        $request->validate([
            'role' => 'required|in:admin,moderator,member',
        ]);

        $member = $board->members()->where('user_id', $userId)->firstOrFail();
        $member->role = $request->input('role');
        $member->save();

        return response()->json([
            'message' => 'Member role updated.',
            'data' => $member,
        ]);
    }

    /**
     * Remove a member from the board (and its group chat).
     *
     * DELETE /api/v1/boards/{board}/members/{user}
     */
    public function removeMember(Request $request, Board $board, string $userId): JsonResponse
    {
        $this->authorizeModerator($request, $board);

        // Prevent removing the only admin
        $member = $board->members()->where('user_id', $userId)->firstOrFail();
        if ($member->isAdmin() && $board->members()->where('role', 'admin')->count() === 1) {
            return response()->json(['message' => 'Cannot remove the only admin.'], 422);
        }

        $board->members()->where('user_id', $userId)->delete();

        // Remove from community group chat
        if ($board->conversation_id) {
            $board->conversation->users()->detach($userId);
        }

        return response()->json(['message' => 'Member removed.']);
    }

    // =============================================
    // JOIN REQUESTS (private communities)
    // =============================================

    /**
     * List pending join requests for a private board.
     *
     * GET /api/v1/boards/{board}/join-requests
     */
    public function listJoinRequests(Request $request, Board $board): JsonResponse
    {
        $this->authorizeModerator($request, $board);

        $requests = $board->joinRequests()
            ->with('user:id,username,role')
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get();

        return response()->json(['data' => $requests]);
    }

    /**
     * Approve or reject a join request.
     *
     * PATCH /api/v1/boards/{board}/join-requests/{joinRequest}
     */
    public function resolveJoinRequest(Request $request, Board $board, BoardJoinRequest $joinRequest): JsonResponse
    {
        $this->authorizeModerator($request, $board);

        abort_if($joinRequest->board_id !== $board->id, 404);

        $request->validate([
            'action' => 'required|in:approve,reject',
        ]);

        if (!$joinRequest->isPending()) {
            return response()->json(['message' => 'This request has already been resolved.'], 422);
        }

        if ($request->input('action') === 'approve') {
            // Add as member
            $board->members()->create([
                'user_id' => $joinRequest->user_id,
                'role' => 'member',
            ]);

            // Add to community group chat
            if ($board->conversation_id) {
                $board->conversation->users()->syncWithoutDetaching([
                    $joinRequest->user_id => ['joined_at' => now()],
                ]);
            }

            $joinRequest->update(['status' => 'approved']);

            return response()->json(['message' => 'Join request approved.']);
        }

        $joinRequest->update(['status' => 'rejected']);

        return response()->json(['message' => 'Join request rejected.']);
    }

    // =============================================
    // POST MODERATION
    // =============================================

    /**
     * List pending (unapproved) posts for a private board.
     *
     * GET /api/v1/boards/{board}/posts/pending
     */
    public function listPendingPosts(Request $request, Board $board): JsonResponse
    {
        $this->authorizeModerator($request, $board);

        $posts = $board->posts()
            ->pending()
            ->with(['user:id,username,role', 'user.profile:id,user_id,avatar_url'])
            ->orderBy('created_at')
            ->get();

        return response()->json(['data' => $posts]);
    }

    /**
     * Approve or reject a pending post.
     *
     * PATCH /api/v1/boards/{board}/posts/{post}/review
     */
    public function reviewPost(Request $request, Board $board, BoardPost $post): JsonResponse
    {
        $this->authorizeModerator($request, $board);

        abort_if($post->board_id !== $board->id, 404);

        $request->validate([
            'action' => 'required|in:approve,reject',
        ]);

        if ($post->status !== 'pending') {
            return response()->json(['message' => 'This post has already been reviewed.'], 422);
        }

        $post->update(['status' => $request->input('action') === 'approve' ? 'approved' : 'rejected']);

        return response()->json([
            'message' => $request->input('action') === 'approve' ? 'Post approved.' : 'Post rejected.',
        ]);
    }

    /**
     * Toggle pin on a post.
     *
     * POST /api/v1/boards/{board}/posts/{post}/pin
     */
    public function togglePin(Request $request, Board $board, BoardPost $post): JsonResponse
    {
        $this->authorizeModerator($request, $board);

        abort_if($post->board_id !== $board->id, 404);

        $post->update(['is_pinned' => !$post->is_pinned]);

        return response()->json([
            'message' => $post->is_pinned ? 'Post pinned.' : 'Post unpinned.',
            'is_pinned' => $post->is_pinned,
        ]);
    }

    /**
     * Delete a post (mod action).
     *
     * DELETE /api/v1/boards/{board}/posts/{post}/moderate
     */
    public function deletePost(Request $request, Board $board, BoardPost $post): JsonResponse
    {
        $this->authorizeModerator($request, $board);

        abort_if($post->board_id !== $board->id, 404);

        $post->delete();

        return response()->json(['message' => 'Post deleted.']);
    }

    // =============================================
    // AUTH HELPERS
    // =============================================

    /**
     * Ensure the authenticated user is a board admin (or platform admin).
     */
    private function authorizeAdmin(Request $request, Board $board): void
    {
        $user = $request->user();
        if ($user->role === 'admin') return; // Platform admin
        abort_if(!$board->userIsAdmin($user->id), 403, 'Board admin access required.');
    }

    /**
     * Ensure the authenticated user is a board admin or moderator (or platform admin).
     */
    private function authorizeModerator(Request $request, Board $board): void
    {
        $user = $request->user();
        if ($user->role === 'admin') return; // Platform admin
        abort_if(!$board->userCanManage($user->id), 403, 'Board moderator access required.');
    }
}
