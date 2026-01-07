<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatRoomController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Api\GiftController;
use App\Http\Controllers\Api\LiveSessionController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\BarController;
use App\Http\Controllers\BarEventController;
use App\Http\Controllers\BarMessageController;
use App\Http\Controllers\LevelController;
use App\Http\Controllers\LiveKitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
| These routes are loaded by the RouteServiceProvider and all of them
| will be assigned to the "api" middleware group.
|
*/

// API Version 1
Route::prefix('v1')->group(function (): void {

    // Public routes - stricter rate limits for auth
    Route::prefix('auth')->middleware('throttle:10,1')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->name('auth.register');
        Route::post('login', [AuthController::class, 'login'])->name('auth.login');
    });

    // Location search (public)
    Route::get('locations/search', [LocationController::class, 'search'])->name('locations.search');

    // User discovery (public)
    Route::get('users/{uuid}', [UserController::class, 'show'])->name('users.show');
    Route::get('users/{uuid}/followers', [FollowController::class, 'followers'])->name('users.followers');
    Route::get('users/{uuid}/following', [FollowController::class, 'following'])->name('users.following');
    Route::get('users/{uuid}/posts', [PostController::class, 'userPosts'])->name('users.posts');
    Route::get('locations/{locationId}/users', [UserController::class, 'byLocation'])->name('locations.users');

    // Content feed (public, but personalized for auth users)
    Route::get('feed', [PostController::class, 'feed'])->name('feed');
    Route::get('posts/{uuid}', [PostController::class, 'show'])->name('posts.show');

    // Protected routes - general rate limit of 120 requests per minute
    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function (): void {

        // Auth
        Route::prefix('auth')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
            Route::get('me', [AuthController::class, 'me'])->name('auth.me');
        });

        // Profile
        Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

        // Follow management
        Route::post('users/{uuid}/follow', [FollowController::class, 'follow'])->name('users.follow');
        Route::delete('users/{uuid}/follow', [FollowController::class, 'unfollow'])->name('users.unfollow');

        // Follow requests (for private profiles)
        Route::get('follow-requests', [FollowController::class, 'pendingRequests'])->name('follow-requests.index');
        Route::post('follow-requests/{uuid}/accept', [FollowController::class, 'acceptRequest'])->name('follow-requests.accept');
        Route::post('follow-requests/{uuid}/reject', [FollowController::class, 'rejectRequest'])->name('follow-requests.reject');

        // Posts management
        Route::post('posts', [PostController::class, 'store'])->name('posts.store');
        Route::put('posts/{uuid}', [PostController::class, 'update'])->name('posts.update');
        Route::delete('posts/{uuid}', [PostController::class, 'destroy'])->name('posts.destroy');

        // Wallet management
        Route::prefix('wallet')->group(function (): void {
            Route::get('/', [WalletController::class, 'show'])->name('wallet.show');
            Route::get('transactions', [WalletController::class, 'transactions'])->name('wallet.transactions');
            Route::get('transaction-types', [WalletController::class, 'types'])->name('wallet.types');

            // Credit purchases and transfers - stricter rate limits (20 per minute)
            Route::middleware('throttle:20,1')->group(function (): void {
                Route::post('transfer', [WalletController::class, 'transfer'])->name('wallet.transfer');
                Route::get('packages', [PurchaseController::class, 'packages'])->name('wallet.packages');
                Route::post('purchase', [PurchaseController::class, 'initiate'])->name('wallet.purchase');
                Route::get('purchase/{uuid}/status', [PurchaseController::class, 'status'])->name('wallet.purchase.status');
                Route::get('purchase/history', [PurchaseController::class, 'history'])->name('wallet.purchase.history');
            });
        });

        // Gift system - stricter rate limits (30 per minute)
        Route::prefix('gifts')->middleware('throttle:30,1')->group(function (): void {
            Route::get('/', [GiftController::class, 'catalog'])->name('gifts.catalog');
            Route::post('send', [GiftController::class, 'send'])->name('gifts.send');
        });

        // Creator earnings
        Route::prefix('earnings')->group(function (): void {
            Route::get('/', [GiftController::class, 'earnings'])->name('earnings.summary');
            Route::get('breakdown', [GiftController::class, 'earningsBreakdown'])->name('earnings.breakdown');
        });

        // Live Sessions (Duels)
        Route::prefix('live-sessions')->group(function (): void {
            Route::get('/', [LiveSessionController::class, 'index'])->name('live-sessions.index');
            Route::get('lobby', [LiveSessionController::class, 'lobby'])->name('live-sessions.lobby');
            Route::post('/', [LiveSessionController::class, 'store'])->name('live-sessions.store');
            Route::get('{uuid}', [LiveSessionController::class, 'show'])->name('live-sessions.show');
            Route::post('{uuid}/join', [LiveSessionController::class, 'join'])->name('live-sessions.join');
            Route::post('{uuid}/end', [LiveSessionController::class, 'end'])->name('live-sessions.end');
            Route::get('{uuid}/scores', [LiveSessionController::class, 'scores'])->name('live-sessions.scores');
            Route::post('{uuid}/gift', [LiveSessionController::class, 'sendGift'])->name('live-sessions.gift');
            Route::get('{uuid}/events', [LiveSessionController::class, 'events'])->name('live-sessions.events');
        });

        // Chat Rooms (Public Kafanas)
        Route::prefix('chat-rooms')->group(function (): void {
            Route::get('/', [ChatRoomController::class, 'index'])->name('chat-rooms.index');
            Route::post('/', [ChatRoomController::class, 'store'])->name('chat-rooms.store');
            Route::get('{uuid}', [ChatRoomController::class, 'show'])->name('chat-rooms.show');
            Route::delete('{uuid}', [ChatRoomController::class, 'destroy'])->name('chat-rooms.destroy');
            Route::get('{uuid}/messages', [ChatRoomController::class, 'messages'])->name('chat-rooms.messages');
            Route::post('{uuid}/messages', [ChatRoomController::class, 'sendMessage'])->name('chat-rooms.sendMessage');
        });

        // Direct Messages (Private Conversations)
        Route::prefix('conversations')->group(function (): void {
            Route::get('/', [ConversationController::class, 'index'])->name('conversations.index');
            Route::post('/', [ConversationController::class, 'store'])->name('conversations.store');
            Route::get('{uuid}', [ConversationController::class, 'show'])->name('conversations.show');
            Route::get('{uuid}/messages', [ConversationController::class, 'messages'])->name('conversations.messages');
            Route::post('{uuid}/messages', [ConversationController::class, 'sendMessage'])->name('conversations.sendMessage');
            Route::post('{uuid}/read', [ConversationController::class, 'markAsRead'])->name('conversations.markAsRead');
            Route::post('{uuid}/mute', [ConversationController::class, 'toggleMute'])->name('conversations.toggleMute');
            Route::post('{uuid}/block', [ConversationController::class, 'block'])->name('conversations.block');
        });

        // Notifications
        Route::prefix('notifications')->group(function (): void {
            Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
            Route::get('summary', [NotificationController::class, 'summary'])->name('notifications.summary');
            Route::get('{uuid}', [NotificationController::class, 'show'])->name('notifications.show');
            Route::post('{uuid}/read', [NotificationController::class, 'markAsRead'])->name('notifications.markAsRead');
            Route::post('read', [NotificationController::class, 'markMultipleAsRead'])->name('notifications.markMultipleAsRead');
            Route::delete('{uuid}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
            Route::delete('/', [NotificationController::class, 'destroyMultiple'])->name('notifications.destroyMultiple');
        });

        // Levels & XP System
        Route::prefix('levels')->group(function (): void {
            Route::get('me', [LevelController::class, 'me'])->name('levels.me');
            Route::get('tiers', [LevelController::class, 'tiers'])->name('levels.tiers');
            Route::get('bar-perks', [LevelController::class, 'barPerks'])->name('levels.barPerks');
            Route::get('can-create-bar', [LevelController::class, 'canCreateBar'])->name('levels.canCreateBar');
        });

        // Bars (Kafane)
        Route::prefix('bars')->group(function (): void {
            Route::get('/', [BarController::class, 'index'])->name('bars.index');
            Route::get('search', [BarController::class, 'search'])->name('bars.search');
            Route::get('my', [BarController::class, 'myBars'])->name('bars.my');
            Route::get('owned', [BarController::class, 'ownedBars'])->name('bars.owned');
            Route::post('/', [BarController::class, 'store'])->name('bars.store');
            Route::get('{bar}', [BarController::class, 'show'])->name('bars.show');
            Route::put('{bar}', [BarController::class, 'update'])->name('bars.update');
            Route::delete('{bar}', [BarController::class, 'destroy'])->name('bars.destroy');

            // Membership
            Route::post('{bar}/join', [BarController::class, 'join'])->name('bars.join');
            Route::post('{bar}/leave', [BarController::class, 'leave'])->name('bars.leave');
            Route::get('{bar}/members', [BarController::class, 'members'])->name('bars.members');

            // Moderation
            Route::post('{bar}/kick', [BarController::class, 'kickMember'])->name('bars.kick');
            Route::post('{bar}/promote', [BarController::class, 'promoteMember'])->name('bars.promote');
            Route::post('{bar}/demote', [BarController::class, 'demoteMember'])->name('bars.demote');
            Route::post('{bar}/mute', [BarController::class, 'muteMember'])->name('bars.mute');

            // Messages
            Route::get('{bar}/messages', [BarMessageController::class, 'index'])->name('bars.messages.index');
            Route::get('{bar}/messages/since', [BarMessageController::class, 'since'])->name('bars.messages.since');
            Route::post('{bar}/messages', [BarMessageController::class, 'store'])->name('bars.messages.store');
            Route::delete('{bar}/messages/{message}', [BarMessageController::class, 'destroy'])->name('bars.messages.destroy');

            // Reactions
            Route::post('{bar}/messages/{message}/reactions', [BarMessageController::class, 'addReaction'])->name('bars.messages.reactions.add');
            Route::delete('{bar}/messages/{message}/reactions', [BarMessageController::class, 'removeReaction'])->name('bars.messages.reactions.remove');

            // Events
            Route::get('{bar}/events', [BarEventController::class, 'index'])->name('bars.events.index');
            Route::post('{bar}/events', [BarEventController::class, 'store'])->name('bars.events.store');
            Route::get('{bar}/events/{event}', [BarEventController::class, 'show'])->name('bars.events.show');
            Route::put('{bar}/events/{event}', [BarEventController::class, 'update'])->name('bars.events.update');
            Route::post('{bar}/events/{event}/start', [BarEventController::class, 'start'])->name('bars.events.start');
            Route::post('{bar}/events/{event}/end', [BarEventController::class, 'end'])->name('bars.events.end');
            Route::post('{bar}/events/{event}/cancel', [BarEventController::class, 'cancel'])->name('bars.events.cancel');
        });

        // Global Events
        Route::get('events/upcoming', [BarEventController::class, 'upcoming'])->name('events.upcoming');
        Route::get('events/live', [BarEventController::class, 'live'])->name('events.live');
        Route::get('events/my', [BarEventController::class, 'myEvents'])->name('events.my');

        // LiveKit WebRTC Streaming
        Route::prefix('livekit')->group(function (): void {
            Route::get('server-info', [LiveKitController::class, 'getServerInfo'])->name('livekit.server-info');
            Route::post('token/streamer', [LiveKitController::class, 'getStreamerToken'])->name('livekit.token.streamer');
            Route::post('token/viewer', [LiveKitController::class, 'getViewerToken'])->name('livekit.token.viewer');
            Route::get('streams/{session}/token', [LiveKitController::class, 'getStreamToken'])->name('livekit.stream.token');
            
            // Stream management
            Route::post('streams', [LiveKitController::class, 'createStream'])->name('livekit.streams.create');
            Route::post('streams/{session}/start', [LiveKitController::class, 'startStream'])->name('livekit.streams.start');
            Route::post('streams/{session}/end', [LiveKitController::class, 'endStream'])->name('livekit.streams.end');
            Route::get('streams/live', [LiveKitController::class, 'getLiveStreams'])->name('livekit.streams.live');
        });
    });

    // Webhooks (public, signature verified)
    Route::prefix('webhooks')->group(function (): void {
        Route::post('raiaccept', [WebhookController::class, 'raiaccept'])->name('webhooks.raiaccept');
    });
});
