<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\Identity\Actions\AcceptFollowRequestAction;
use App\Domains\Identity\Actions\FollowUserAction;
use App\Domains\Identity\Actions\GetFollowersAction;
use App\Domains\Identity\Actions\GetFollowingAction;
use App\Domains\Identity\Actions\GetPendingFollowRequestsAction;
use App\Domains\Identity\Actions\GetUserByUuidAction;
use App\Domains\Identity\Actions\RejectFollowRequestAction;
use App\Domains\Identity\Actions\UnfollowUserAction;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Resources\FollowResource;
use App\Domains\Identity\Resources\UserResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FollowController extends Controller
{
    public function __construct(
        private readonly GetUserByUuidAction $getUserByUuid,
    ) {}

    /**
     * Follow a user.
     */
    public function follow(
        Request $request,
        string $uuid,
        FollowUserAction $action,
    ): JsonResponse {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $targetUser = $this->getUserByUuid->execute($uuid, false);

        if (! $targetUser) {
            return response()->json(['message' => 'User not found'], 404);
        }

        try {
            $follow = $action->execute($currentUser, $targetUser);

            return response()->json([
                'data' => new FollowResource($follow),
                'message' => $follow->accepted_at
                    ? 'Now following user'
                    : 'Follow request sent',
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Unfollow a user.
     */
    public function unfollow(
        Request $request,
        string $uuid,
        UnfollowUserAction $action,
    ): JsonResponse {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $targetUser = $this->getUserByUuid->execute($uuid, false);

        if (! $targetUser) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $deleted = $action->execute($currentUser, $targetUser);

        if (! $deleted) {
            return response()->json(['message' => 'Not following this user.'], 422);
        }

        return response()->json(['message' => 'Successfully unfollowed user.']);
    }

    /**
     * Get followers of a user.
     */
    public function followers(
        Request $request,
        string $uuid,
        GetFollowersAction $action,
    ): JsonResponse {
        $targetUser = $this->getUserByUuid->execute($uuid, false);

        if (! $targetUser) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $followers = $action->execute(
            $targetUser,
            acceptedOnly: true,
            perPage: min((int) $request->query('per_page', 20), 100),
        );

        return response()->json([
            'data' => UserResource::collection($followers->items()),
            'meta' => [
                'currentPage' => $followers->currentPage(),
                'lastPage' => $followers->lastPage(),
                'perPage' => $followers->perPage(),
                'total' => $followers->total(),
            ],
        ]);
    }

    /**
     * Get users that a user is following.
     */
    public function following(
        Request $request,
        string $uuid,
        GetFollowingAction $action,
    ): JsonResponse {
        $targetUser = $this->getUserByUuid->execute($uuid, false);

        if (! $targetUser) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $following = $action->execute(
            $targetUser,
            acceptedOnly: true,
            perPage: min((int) $request->query('per_page', 20), 100),
        );

        return response()->json([
            'data' => UserResource::collection($following->items()),
            'meta' => [
                'currentPage' => $following->currentPage(),
                'lastPage' => $following->lastPage(),
                'perPage' => $following->perPage(),
                'total' => $following->total(),
            ],
        ]);
    }

    /**
     * Get pending follow requests for the current user.
     */
    public function pendingRequests(
        Request $request,
        GetPendingFollowRequestsAction $action,
    ): JsonResponse {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $requests = $action->execute(
            $currentUser,
            perPage: min((int) $request->query('per_page', 20), 100),
        );

        return response()->json([
            'data' => UserResource::collection($requests->items()),
            'meta' => [
                'currentPage' => $requests->currentPage(),
                'lastPage' => $requests->lastPage(),
                'perPage' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Accept a follow request.
     */
    public function acceptRequest(
        Request $request,
        string $uuid,
        AcceptFollowRequestAction $action,
    ): JsonResponse {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $follower = $this->getUserByUuid->execute($uuid, false);

        if (! $follower) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $accepted = $action->execute($currentUser, $follower);

        if (! $accepted) {
            return response()->json(['message' => 'No pending follow request from this user.'], 422);
        }

        return response()->json(['message' => 'Follow request accepted.']);
    }

    /**
     * Reject a follow request.
     */
    public function rejectRequest(
        Request $request,
        string $uuid,
        RejectFollowRequestAction $action,
    ): JsonResponse {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $follower = $this->getUserByUuid->execute($uuid, false);

        if (! $follower) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $rejected = $action->execute($currentUser, $follower);

        if (! $rejected) {
            return response()->json(['message' => 'No pending follow request from this user.'], 422);
        }

        return response()->json(['message' => 'Follow request rejected.']);
    }
}
