<?php

declare(strict_types=1);

use App\Domains\Duel\Models\ChatRoom;
use App\Domains\Duel\Models\LiveSession;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| User Private Channel
|--------------------------------------------------------------------------
|
| This channel is used for private notifications to a specific user.
| Only the authenticated user can subscribe to their own channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return $user->id === $id;
});

/*
|--------------------------------------------------------------------------
| Chat Room Presence Channel
|--------------------------------------------------------------------------
|
| Public kafana and duel chat rooms. Any authenticated user can join
| public rooms. Presence channel tracks who is currently in the room.
|
*/

Broadcast::channel('chat.{roomUuid}', function (User $user, string $roomUuid): array|false {
    $room = ChatRoom::where('uuid', $roomUuid)->first();

    if (! $room || ! $room->is_active) {
        return false;
    }

    // Return user info for presence channel
    return [
        'id' => $user->id,
        'uuid' => $user->uuid,
        'username' => $user->username,
        'avatar_url' => $user->profile?->avatar_url,
    ];
});

/*
|--------------------------------------------------------------------------
| Live Duel Presence Channel
|--------------------------------------------------------------------------
|
| Real-time duel sessions. All authenticated users can watch duels.
| Presence channel tracks viewers, hosts, and guests.
|
*/

Broadcast::channel('duel.{sessionUuid}', function (User $user, string $sessionUuid): array|false {
    $session = LiveSession::where('uuid', $sessionUuid)->first();

    if (! $session) {
        return false;
    }

    // Determine user's role in the duel
    $role = 'viewer';
    if ($session->host_id === $user->id) {
        $role = 'host';
    } elseif ($session->guest_id === $user->id) {
        $role = 'guest';
    }

    return [
        'id' => $user->id,
        'uuid' => $user->uuid,
        'username' => $user->username,
        'avatar_url' => $user->profile?->avatar_url,
        'role' => $role,
    ];
});

/*
|--------------------------------------------------------------------------
| Duel Scoreboard Channel
|--------------------------------------------------------------------------
|
| Public channel for duel score updates. Anyone can listen for score
| changes without joining the presence channel.
|
*/

Broadcast::channel('duel.{sessionUuid}.scores', function (User $user, string $sessionUuid): bool {
    // Any authenticated user can watch scores
    return LiveSession::where('uuid', $sessionUuid)->exists();
});

/*
|--------------------------------------------------------------------------
| Private Notifications Channel
|--------------------------------------------------------------------------
|
| Private channel for user-specific notifications like gifts received,
| new followers, duel invitations, etc.
|
*/

Broadcast::channel('notifications.{userId}', function (User $user, int $userId): bool {
    return $user->id === $userId;
});

/*
|--------------------------------------------------------------------------
| Direct Message Channel
|--------------------------------------------------------------------------
|
| Private channel for direct message conversations between two users.
| Only participants can subscribe to receive messages.
|
*/

Broadcast::channel('dm.{roomUuid}', function (User $user, string $roomUuid): array|false {
    $room = ChatRoom::where('uuid', $roomUuid)->first();

    if (! $room || ! $room->isDirectMessage()) {
        return false;
    }

    // Check if user is a participant
    if (! $room->hasParticipant($user)) {
        return false;
    }

    return [
        'id' => $user->id,
        'uuid' => $user->uuid,
        'username' => $user->profile?->username,
        'avatar_url' => $user->profile?->avatar_url,
    ];
});

/*
|--------------------------------------------------------------------------
| Kafana Presence Channel
|--------------------------------------------------------------------------
|
| Public kafana rooms. Any authenticated user can join.
| Used for presence tracking in public chat rooms.
|
*/

Broadcast::channel('kafana.{roomUuid}', function (User $user, string $roomUuid): array|false {
    $room = ChatRoom::where('uuid', $roomUuid)->first();

    if (! $room || ! $room->is_active || ! $room->isKafana()) {
        return false;
    }

    return [
        'id' => $user->id,
        'uuid' => $user->uuid,
        'username' => $user->profile?->username,
        'avatar_url' => $user->profile?->avatar_url,
    ];
});
