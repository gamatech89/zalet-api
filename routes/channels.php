<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. Private and presence channels require
| authentication via Sanctum token.
|
*/

/**
 * Private user channel for personal notifications (new followers, etc.)
 */
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return $user->id === $userId;
});

/**
 * Private conversation channel for messaging
 * User must be a participant in the conversation
 */
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    return $user->conversations()->where('conversations.id', $conversationId)->exists();
});

/**
 * Private stream channel for gifting alerts
 * Only the stream owner receives gift alerts
 */
Broadcast::channel('stream.{streamId}', function ($user, $streamId) {
    return $user->liveStreams()->where('id', $streamId)->exists();
});

/**
 * Public stream chat channel
 * All authenticated users can join to watch stream chat
 */
Broadcast::channel('stream-chat.{streamId}', function ($user) {
    return ['id' => $user->id, 'username' => $user->username];
});
