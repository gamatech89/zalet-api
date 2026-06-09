<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AdminGiftController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BankAccountController;
use App\Http\Controllers\Api\V1\BoardController;
use App\Http\Controllers\Api\V1\BoardPostController;
use App\Http\Controllers\Api\V1\BoardAdminController;
use App\Http\Controllers\Api\V1\CinemaController;
use App\Http\Controllers\Api\V1\CoinPackageController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\CreatorController;
use App\Http\Controllers\Api\V1\FollowController;
use App\Http\Controllers\Api\V1\GiftController;
use App\Http\Controllers\Api\V1\LiveStreamController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\MediaPurchaseController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\MomentController;
use App\Http\Controllers\Api\V1\ScenaController;
use App\Http\Controllers\Api\V1\PaymentCallbackController;
use App\Http\Controllers\Api\V1\PaymentMethodController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\StreamGiftController;
use App\Http\Controllers\Api\V1\StreamScheduleController;
use App\Http\Controllers\Api\V1\StreamGoalController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\SubscriptionPlanController;
use App\Http\Controllers\Api\V1\CreatorRequestController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\NotificationController;
use Illuminate\Support\Facades\Route;

/*
 |--------------------------------------------------------------------------
 | API Routes
 |--------------------------------------------------------------------------
 |
 | All routes are prefixed with /api and use the Sanctum authentication
 | guard where required. Routes are versioned under /api/v1/.
 |
 */

// API v1 Routes
Route::prefix('v1')->group(function () {

    /*
     |--------------------------------------------------------------------------
     | Public Routes (No Authentication Required)
     |--------------------------------------------------------------------------
     */

    // Health check
    Route::get('/health', function () {
            return response()->json([
            'status' => 'ok',
            'version' => 'v1',
            'timestamp' => now()->toIso8601String(),
            ]);
        }
        );

        // Auth routes (public) - strict rate limiting
        Route::prefix('auth')->middleware('throttle:auth')->group(function () {
            Route::post('/register', [AuthController::class , 'register']);
            Route::post('/login', [AuthController::class , 'login']);
            Route::post('/forgot-password', [AuthController::class , 'forgotPassword']);
        }
        );

        // Gift catalog (public - anyone can view) - moderate rate limiting
        Route::get('/gifts', [GiftController::class , 'index'])->middleware('throttle:public');

        // Location Discovery (public)
        Route::prefix('locations')->middleware('throttle:public')->group(function () {
            Route::get('/search', [LocationController::class , 'search']);
            Route::get('/places/{place}', [LocationController::class , 'show']);
            Route::post('/save-google-place', [LocationController::class , 'saveGooglePlace']);
        }
        );

        // Discovery Hubs (public)
        Route::prefix('hubs')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\HubController::class , 'index']);
            Route::get('/{country}', [\App\Http\Controllers\Api\V1\HubController::class , 'show']);
            Route::get('/{country}/users', [\App\Http\Controllers\Api\V1\HubController::class , 'users']);
        }
        );

        // Public User Profiles
        Route::get('/users/suggested', [\App\Http\Controllers\Api\V1\UserController::class , 'suggested']);
        Route::get('/users/{user}', [\App\Http\Controllers\Api\V1\UserController::class , 'show']);

        // Payment webhooks (public - verified via signature)
        Route::prefix('webhooks')->group(function () {
            Route::post('/raiffeisen', [PaymentWebhookController::class , 'handleNotification']);
        });

        // Payment callbacks (public - browser redirects after payment)
        Route::match(['get', 'post'], '/payment/success', [PaymentCallbackController::class, 'success']);
        Route::match(['get', 'post'], '/payment/failure', [PaymentCallbackController::class, 'failure']);
        // Aliases for Merchant Portal configured URLs
        Route::match(['get', 'post'], '/payment/callback/success', [PaymentCallbackController::class, 'success']);
        Route::match(['get', 'post'], '/payment/callback/failure', [PaymentCallbackController::class, 'failure']);

        // Payment redirect (renders auto-submitting POST form to Raiffeisen gateway)
        Route::get('/payment/redirect/{cacheKey}', [\App\Http\Controllers\Api\V1\PaymentRedirectController::class, 'show']);

        // Mock bank gateway (local development only)
        if (app()->environment('local')) {
            Route::match(['get', 'post'], '/mock-bank/pay', [\App\Http\Controllers\Api\V1\MockRaiffeisenController::class, 'showPaymentForm']);
            Route::post('/mock-bank/process', [\App\Http\Controllers\Api\V1\MockRaiffeisenController::class, 'processPayment']);
        }

        // Coin packages (public - anyone can view)
        Route::get('/coin-packages', [CoinPackageController::class, 'index']);

        // Moments feed (public - anyone can view free content)
        Route::get('/moments', [MomentController::class , 'index']);
        Route::get('/moments/{media}', [MomentController::class , 'show']);

        // Scena feed (public - long-form content)
        Route::get('/scena', [ScenaController::class , 'index']);
        Route::get('/scena/{media}', [ScenaController::class , 'show']);

        // Global search (public)
        Route::get('/search', [\App\Http\Controllers\Api\V1\SearchController::class , 'index']);

        // Tags (public)
        Route::get('/tags', function () {
            return response()->json(\App\Models\Tag::orderBy('name')->get());
        }
        );

        Route::get('/cinema', [CinemaController::class , 'index']);
        Route::get('/cinema/{media}', [CinemaController::class , 'show']);

        // Subscription Plans (public - anyone can view available plans)
        Route::get('/subscription-plans', [SubscriptionPlanController::class , 'index']);

        // Community Boards (public - read access)
        Route::prefix('boards')->group(function () {
            Route::get('/', [BoardController::class , 'index']);
            Route::get('/plan-info', [BoardPostController::class, 'getCurrentPlanInfo'])->middleware('auth:sanctum');
            Route::post('/', [BoardController::class, 'store'])->middleware('auth:sanctum');
            Route::get('/{board:slug}', [BoardController::class , 'show']);
            Route::get('/{board:slug}/posts', [BoardPostController::class , 'index']);
            Route::get('/{board:slug}/posts/{post}', [BoardPostController::class , 'show']);
            Route::get('/{board:slug}/categories', [BoardAdminController::class , 'listCategories']);
            Route::get('/{board:slug}/members', [BoardAdminController::class , 'listMembers']);
            Route::post('/{board:slug}/join', [BoardController::class, 'join'])->middleware('auth:sanctum');
            Route::post('/{board:slug}/leave', [BoardController::class, 'leave'])->middleware('auth:sanctum');
        });

        // Live Streaming (public - anyone can browse & watch)
        Route::prefix('streams')->group(function () {
            Route::get('/live', [LiveStreamController::class , 'live']);
            Route::get('/{liveStream}', [LiveStreamController::class , 'show'])
                ->where('liveStream', '[0-9a-f\-]{36}');
            Route::get('/{liveStream}/token', [LiveStreamController::class , 'viewerToken'])
                ->where('liveStream', '[0-9a-f\-]{36}');
        });

        /*
     |--------------------------------------------------------------------------
     | Protected Routes (Authentication Required)
     |--------------------------------------------------------------------------
     */

        Route::middleware('auth:sanctum')->group(function () {

            // Auth routes (protected)
            Route::prefix('auth')->group(function () {
                    Route::post('/logout', [AuthController::class , 'logout']);
                    Route::get('/me', [AuthController::class , 'me']);
                }
                );

                // Profile routes
                Route::prefix('profile')->group(function () {
                    Route::get('/', [ProfileController::class , 'show']);
                    Route::put('/', [ProfileController::class , 'update']);
                    Route::post('/avatar', [ProfileController::class , 'uploadAvatar']);
                    Route::post('/cover', [ProfileController::class , 'uploadCover']);
                }
                );

                // Wallet routes (Sprint 2)
                Route::prefix('wallet')->group(function () {
                    Route::get('/', [WalletController::class , 'show']);
                    Route::get('/transactions', [WalletController::class , 'transactions']);
                    Route::get('/transactions/{transaction}', [WalletController::class , 'showTransaction']);
                    Route::post('/transfer', [WalletController::class , 'transfer']);
                    Route::post('/deposit', [WalletController::class , 'deposit']);
                    Route::post('/withdraw', [WalletController::class , 'withdraw']);
                    Route::get('/withdrawal-preview', [WalletController::class , 'withdrawalPreview']);
                });

                // Payment Methods (saved cards)
                Route::prefix('payment-methods')->group(function () {
                    Route::get('/', [PaymentMethodController::class, 'index']);
                    Route::post('/', [PaymentMethodController::class, 'store']);
                    Route::put('/{paymentMethod}/default', [PaymentMethodController::class, 'setDefault']);
                    Route::delete('/', [PaymentMethodController::class, 'destroyAll']);
                    Route::delete('/{paymentMethod}', [PaymentMethodController::class, 'destroy']);
                });

                // Bank Accounts (for withdrawals)
                Route::prefix('bank-accounts')->group(function () {
                    Route::get('/', [BankAccountController::class, 'index']);
                    Route::post('/', [BankAccountController::class, 'store']);
                    Route::put('/{bankAccount}/default', [BankAccountController::class, 'setDefault']);
                    Route::delete('/{bankAccount}', [BankAccountController::class, 'destroy']);
                });

                // Gift routes (Sprint 2)
                Route::get('/gifts/album', [\App\Http\Controllers\Api\V1\GiftAlbumController::class, 'index']);
                Route::post('/gifts/send', [GiftController::class , 'send']);

                // Moments routes - all authenticated users can post moments
                Route::post('/moments', [MomentController::class , 'store']);
                Route::delete('/moments/{media}', [MomentController::class , 'destroy']);

                // Community Board Posts (Sprint 7) - protected
                Route::prefix('boards/{board:slug}/posts')->group(function () {
                    Route::post('/', [BoardPostController::class , 'store']);
                    Route::delete('/{post}', [BoardPostController::class , 'destroy']);
                    Route::post('/{post}/like', [BoardPostController::class , 'toggleLike']);
                    Route::post('/{post}/comments', [BoardPostController::class , 'addComment']);
                });

                // PPV & Subscriptions (Sprint 3)
                Route::post('/media/{media}/purchase', [MediaPurchaseController::class , 'store']);

                // Media interactions (likes, bookmarks, views)
                Route::post('/media/{media}/like', [\App\Http\Controllers\Api\V1\MediaInteractionController::class, 'toggleLike']);
                Route::post('/media/{media}/bookmark', [\App\Http\Controllers\Api\V1\MediaInteractionController::class, 'toggleBookmark']);
                Route::post('/media/{media}/view', [\App\Http\Controllers\Api\V1\MediaInteractionController::class, 'trackView']);
                Route::get('/profile/bookmarks', [\App\Http\Controllers\Api\V1\MediaInteractionController::class, 'listBookmarks']);

                // Media comments
                Route::get('/media/{media}/comments', [\App\Http\Controllers\Api\V1\MediaCommentController::class, 'index']);
                Route::post('/media/{media}/comments', [\App\Http\Controllers\Api\V1\MediaCommentController::class, 'store']);
                Route::delete('/media/{media}/comments/{comment}', [\App\Http\Controllers\Api\V1\MediaCommentController::class, 'destroy']);
                // Subscriptions (global plan-based)
                Route::prefix('subscriptions')->group(function () {
                    Route::post('/', [SubscriptionController::class , 'subscribe']);
                    Route::get('/current', [SubscriptionController::class , 'current']);
                    Route::post('/cancel', [SubscriptionController::class , 'cancel']);
                    Route::post('/change-plan', [SubscriptionController::class , 'changePlan']);
                });

                // Plan limits & usage
                Route::get('/plan/limits', function (\Illuminate\Http\Request $request) {
                    $service = app(\App\Services\PlanLimitsService::class);
                    return response()->json($service->getPlanInfo($request->user()));
                });

                // Creator Requests (become a creator)
                Route::post('/creator-requests', [CreatorRequestController::class , 'store']);
                Route::get('/creator-requests/mine', [CreatorRequestController::class , 'show']);

                // Follow System (Sprint 4)
                Route::post('/users/{user}/follow', [FollowController::class , 'follow']);
                Route::delete('/users/{user}/follow', [FollowController::class , 'unfollow']);
                Route::get('/users/{user}/followers', [FollowController::class , 'followers']);
                Route::get('/users/{user}/following', [FollowController::class , 'following']);

                // Live Streaming — viewer interactions (all authenticated users)
                Route::prefix('streams')->group(function () {
                    Route::post('/{liveStream}/chat', [LiveStreamController::class , 'sendChat']);
                    Route::post('/{liveStream}/gift', [StreamGiftController::class , 'store']);
                });

                // Creator-only content routes
                Route::middleware('creator')->group(function () {
                    // Cinema embeds (YouTube/Vimeo/Dailymotion) - creator only
                    Route::post('/cinema', [CinemaController::class , 'store']);
                    Route::delete('/cinema/{media}', [CinemaController::class , 'destroy']);

                    // Scena (long-form video upload + embed) - creator only
                    Route::post('/scena/embed', [ScenaController::class , 'embed']);
                    Route::post('/scena', [ScenaController::class , 'store']);
                    Route::post('/scena/{media}', [ScenaController::class , 'update']);
                    Route::delete('/scena/{media}', [ScenaController::class , 'destroy']);

                    // Live Streaming management - creator only
                    Route::prefix('streams')->group(function () {
                        Route::post('/', [LiveStreamController::class , 'store']);
                        Route::get('/key', [LiveStreamController::class , 'getStreamKey']);
                        Route::post('/start', [LiveStreamController::class , 'start']);
                        Route::post('/stop', [LiveStreamController::class , 'stop']);
                        Route::post('/{liveStream}/thumbnail', [LiveStreamController::class , 'uploadThumbnail']);
                        Route::post('/{liveStream}/recording', [LiveStreamController::class , 'uploadRecording']);
                        Route::delete('/{liveStream}/recording', [LiveStreamController::class , 'discardRecording']);
                        // Stream scheduling
                        Route::get('/scheduled', [StreamScheduleController::class , 'index']);
                        Route::post('/schedule', [StreamScheduleController::class , 'store']);
                        Route::delete('/schedule/{liveStream}', [StreamScheduleController::class , 'destroy']);
                        // Stream goals
                        Route::put('/{liveStream}/goals', [StreamGoalController::class , 'update']);
                        Route::post('/{liveStream}/goals/{index}/progress', [StreamGoalController::class , 'progress']);
                    });
                });

                // Board Admin (auth required)
                Route::prefix('boards/{board:slug}')->group(function () {
                    Route::post('/posts', [BoardPostController::class , 'store']);
                    Route::post('/upload-image', [BoardPostController::class , 'uploadImage']);
                    Route::delete('/posts/{post}', [BoardPostController::class , 'destroy']);
                    Route::post('/posts/{post}/like', [BoardPostController::class , 'toggleLike']);
                    Route::post('/posts/{post}/comments', [BoardPostController::class , 'addComment']);
                    // Admin/Moderator actions
                    Route::post('/posts/{post}/pin', [BoardAdminController::class , 'togglePin']);
                    Route::delete('/posts/{post}/moderate', [BoardAdminController::class , 'deletePost']);
                    Route::get('/posts/pending', [BoardAdminController::class , 'listPendingPosts']);
                    Route::patch('/posts/{post}/review', [BoardAdminController::class , 'reviewPost']);
                    Route::post('/categories', [BoardAdminController::class , 'createCategory']);
                    Route::patch('/categories/{category}', [BoardAdminController::class , 'updateCategory']);
                    Route::delete('/categories/{category}', [BoardAdminController::class , 'deleteCategory']);
                    Route::post('/members', [BoardAdminController::class , 'addMember']);
                    Route::patch('/members/{user}', [BoardAdminController::class , 'updateMember']);
                    Route::delete('/members/{user}', [BoardAdminController::class , 'removeMember']);
                    // Join requests (private communities)
                    Route::get('/join-requests', [BoardAdminController::class , 'listJoinRequests']);
                    Route::patch('/join-requests/{joinRequest}', [BoardAdminController::class , 'resolveJoinRequest']);
                });

                // Notifications
                Route::prefix('notifications')->group(function () {
                    Route::get('/', [NotificationController::class, 'index']);
                    Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
                    Route::post('/{notification}/read', [NotificationController::class, 'markRead']);
                    Route::post('/read-all', [NotificationController::class, 'markAllRead']);
                });

                // Messaging (Sprint 4)
                Route::prefix('conversations')->group(function () {
                    Route::get('/', [ConversationController::class, 'index']);
                    Route::post('/', [ConversationController::class, 'store']);
                    Route::get('/unread-count', [ConversationController::class, 'unreadCount']);
                    // /join/{inviteCode} must come before /{conversation} to avoid UUID binding collision
                    Route::get('/join/{inviteCode}', [ConversationController::class, 'joinByCode']);
                    Route::get('/{conversation}', [ConversationController::class, 'show']);
                    Route::patch('/{conversation}', [ConversationController::class, 'update']);
                    Route::get('/{conversation}/messages', [MessageController::class, 'index']);
                    Route::post('/{conversation}/messages', [MessageController::class, 'store']);
                    Route::post('/{conversation}/messages/{message}/reactions', [MessageController::class, 'addReaction']);
                    Route::post('/{conversation}/typing', [MessageController::class, 'typing']);
                    // Group member management
                    Route::post('/{conversation}/members', [ConversationController::class, 'addMembers']);
                    Route::delete('/{conversation}/members/{member}', [ConversationController::class, 'kickMember']);
                    Route::patch('/{conversation}/members/{member}/role', [ConversationController::class, 'updateMemberRole']);
                    // Group ban management
                    Route::post('/{conversation}/bans', [ConversationController::class, 'banMember']);
                    Route::delete('/{conversation}/bans/{member}', [ConversationController::class, 'unbanMember']);
                    // Leave group
                    Route::delete('/{conversation}/leave', [ConversationController::class, 'leave']);
                });

                // Admin Routes (Sprint 5)
                Route::prefix('admin')
                    ->middleware('admin')
                    ->group(function () {
                Route::get('/stats', [AdminController::class , 'stats']);
                Route::get('/users', [AdminController::class , 'listUsers']);
                Route::patch('/users/{user}', [AdminController::class , 'updateUser']);
                Route::post('/users/{user}/founder', [AdminController::class , 'markFounder']);
                Route::get('/transactions', [AdminController::class , 'listTransactions']);
                Route::get('/media', [AdminController::class , 'listMedia']);
                Route::delete('/media/{media}', [AdminController::class , 'deleteMedia']);
                Route::get('/streams', [AdminController::class , 'listStreams']);
                Route::post('/streams/{liveStream}/end', [AdminController::class , 'endStream']);

                // Community approval
                Route::get('/communities/pending', [AdminController::class, 'listPendingCommunities']);
                Route::patch('/communities/{board}', [AdminController::class, 'reviewCommunity']);

                // Gift Management
                Route::patch('/gifts/reorder', [AdminGiftController::class, 'reorder']);
                Route::get('/gifts', [AdminGiftController::class, 'index']);
                Route::post('/gifts', [AdminGiftController::class, 'store']);
                Route::get('/gifts/{gift}', [AdminGiftController::class, 'show']);
                Route::put('/gifts/{gift}', [AdminGiftController::class, 'update']);
                Route::delete('/gifts/{gift}', [AdminGiftController::class, 'destroy']);
                Route::post('/gifts/{gift}/icon', [AdminGiftController::class, 'uploadIcon']);

                // Gift Categories
                Route::get('/gift-categories', [AdminGiftController::class, 'categories']);
                Route::post('/gift-categories', [AdminGiftController::class, 'storeCategory']);
                Route::put('/gift-categories/{giftCategory}', [AdminGiftController::class, 'updateCategory']);
                Route::delete('/gift-categories/{giftCategory}', [AdminGiftController::class, 'destroyCategory']);
            }
            );

            // Creator Routes (Sprint 6)
            Route::prefix('creator')
                ->middleware('creator')
                ->group(function () {
                Route::get('/stats', [CreatorController::class , 'stats']);
                Route::get('/earnings', [CreatorController::class , 'earnings']);
                Route::get('/subscribers', [CreatorController::class , 'subscribers']);
                Route::get('/content', [CreatorController::class , 'content']);
                Route::get('/streams/history', [CreatorController::class , 'streamHistory']);
                Route::get('/analytics', [CreatorController::class , 'analytics']);
                Route::get('/top-supporters', [CreatorController::class , 'topSupporters']);
            }
            );

        }
        );

    });