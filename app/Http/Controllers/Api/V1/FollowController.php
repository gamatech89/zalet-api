<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventType;
use App\Events\NewFollowerEvent;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserEvent;
use App\Services\Achievements\Payloads\FollowerGainedPayload;
use App\Services\Achievements\Payloads\UserFollowedPayload;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    /**
     * Follow a user.
     * POST /api/v1/users/{user}/follow
     */
    public function follow(Request $request, string $key): JsonResponse
    {
        $user = $this->resolveUser($key);
        $currentUser = $request->user();

        // Cannot follow yourself
        if ($currentUser->id === $user->id) {
            return response()->json([
                'message' => 'You cannot follow yourself.',
            ], 422);
        }

        // Check if already following
        if ($currentUser->following()->where('following_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'You are already following this user.',
            ], 409);
        }

        // Create follow relationship
        $currentUser->following()->attach($user->id);

        // Broadcast new follower event
        broadcast(new NewFollowerEvent($currentUser, $user))->toOthers();

        UserEvent::record($currentUser, EventType::USER_FOLLOWED, new UserFollowedPayload(
            followedId: $user->id,
        ));

        UserEvent::record($user, EventType::FOLLOWER_GAINED, new FollowerGainedPayload(
            followerId: $currentUser->id,
        ));

        // Create notification for the followed user
        app(NotificationService::class)->create(
            $user,
            'follow',
            'Novi pratilac',
            "@{$currentUser->username} vas sada prati.",
            ['follower_id' => $currentUser->id, 'follower_username' => $currentUser->username],
        );

        return response()->json([
            'message' => 'Successfully followed user.',
            'following' => [
                'id' => $user->id,
                'username' => $user->username,
            ],
        ], 201);
    }

    /**
     * Unfollow a user.
     * DELETE /api/v1/users/{user}/follow
     */
    public function unfollow(Request $request, string $key): JsonResponse
    {
        $user = $this->resolveUser($key);
        $currentUser = $request->user();

        // Check if following
        if (!$currentUser->following()->where('following_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'You are not following this user.',
            ], 404);
        }

        // Remove follow relationship
        $currentUser->following()->detach($user->id);

        return response()->json([
            'message' => 'Successfully unfollowed user.',
        ]);
    }

    /**
     * Get followers of a user.
     * GET /api/v1/users/{user}/followers
     */
    public function followers(string $key): JsonResponse
    {
        $user = $this->resolveUser($key);
        $followers = $user->followers()
            ->select('users.id', 'users.username', 'users.role')
            ->withPivot('created_at')
            ->orderByPivot('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $followers->items(),
            'meta' => [
                'current_page' => $followers->currentPage(),
                'last_page' => $followers->lastPage(),
                'per_page' => $followers->perPage(),
                'total' => $followers->total(),
            ],
        ]);
    }

    /**
     * Get users that a user is following.
     * GET /api/v1/users/{user}/following
     */
    public function following(string $key): JsonResponse
    {
        $user = $this->resolveUser($key);
        $following = $user->following()
            ->select('users.id', 'users.username', 'users.role')
            ->withPivot('created_at')
            ->orderByPivot('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $following->items(),
            'meta' => [
                'current_page' => $following->currentPage(),
                'last_page' => $following->lastPage(),
                'per_page' => $following->perPage(),
                'total' => $following->total(),
            ],
        ]);
    }

    private function resolveUser(string $key): User
    {
        $query = User::query();
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $key)) {
            $query->where('id', $key);
        }
        else {
            $query->where('username', $key);
        }
        return $query->firstOrFail();
    }
}