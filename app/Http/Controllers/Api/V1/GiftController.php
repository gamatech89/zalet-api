<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\MessageSentEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendGiftRequest;
use App\Models\Conversation;
use App\Models\Gift;
use App\Models\GiftCategory;
use App\Models\User;
use App\Services\CoinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class GiftController extends Controller
{
    public function __construct(
        protected CoinService $coinService,
    ) {}

    /**
     * Get the gift catalog.
     *
     * GET /api/v1/gifts
     */
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'gifts.catalog.v5' . ($request->filled('category') ? '.' . $request->category : '');

        $data = Cache::remember($cacheKey, 3600, function () use ($request) {
            $query = Gift::active()->with('category')->ordered();

            if ($request->filled('category')) {
                $query->category($request->category);
            }

            return [
                'gifts' => $query->get([
                    'id', 'name', 'coin_price', 'icon_url',
                    'icon_2d', 'icon_3d', 'category_id',
                    'description', 'sort_order', 'level',
                    'is_epic', 'is_rare',
                ]),
                'categories' => GiftCategory::active()
                    ->ordered()
                    ->get(['id', 'name', 'slug']),
            ];
        });

        return response()->json([
            'data' => $data['gifts'],
            'categories' => $data['categories'],
        ]);
    }

    /**
     * Send a gift to a user.
     *
     * POST /api/v1/gifts/send
     */
    public function send(SendGiftRequest $request): JsonResponse
    {
        $sender = $request->user();
        $recipient = User::findOrFail($request->validated('recipient_id'));
        $gift = Gift::active()->findOrFail($request->validated('gift_id'));

        try {
            $transaction = $this->coinService->tip(
                fromUser: $sender,
                toUser: $recipient,
                amount: $gift->coin_price,
                gift: $gift,
            );

            $conversationId = $request->validated('conversation_id');

            if ($conversationId) {
                // Group gift: post to the provided group conversation
                $conversation = Conversation::findOrFail($conversationId);
                if (!$conversation->users()->where('users.id', $sender->id)->exists()) {
                    return response()->json(['message' => 'Not a member of this conversation.'], 403);
                }
            } else {
                // DM gift: find or create the DM conversation
                $conversation = $sender->conversations()
                    ->where('is_group', false)
                    ->whereHas('users', fn ($q) => $q->where('users.id', $recipient->id))
                    ->first();

                if (!$conversation) {
                    $conversation = Conversation::create(['is_group' => false]);
                    $conversation->users()->attach([
                        $sender->id => ['joined_at' => now()],
                        $recipient->id => ['joined_at' => now()],
                    ]);
                }
            }

            // Always include recipient info so group chats can display "X → Y"
            $giftContent = json_encode([
                'name' => $gift->name,
                'coin_price' => (float) $gift->coin_price,
                'icon_url' => $gift->icon_url,
                'icon_3d' => $gift->icon_3d,
                'description' => $gift->description,
                'recipient_id' => $recipient->id,
                'recipient_username' => $recipient->username,
            ]);

            $message = $conversation->messages()->create([
                'sender_id' => $sender->id,
                'message_type' => 'gift',
                'content' => $giftContent,
            ]);
            $message->load(['sender:id,username', 'reactions']);
            $conversation->touch();

            // Broadcast to the recipient via the conversation channel
            broadcast(new MessageSentEvent($message))->toOthers();

            return response()->json([
                'message' => 'Gift sent successfully!',
                'data' => [
                    'transaction_id' => $transaction->id,
                    'conversation_id' => $conversation->id,
                    'gift' => [
                        'id' => $gift->id,
                        'name' => $gift->name,
                        'icon_url' => $gift->icon_url,
                        'icon_2d' => $gift->icon_2d,
                        'icon_3d' => $gift->icon_3d,
                    ],
                    'recipient' => [
                        'id' => $recipient->id,
                        'username' => $recipient->username,
                    ],
                    'amount' => $gift->coin_price,
                    'new_balance' => $this->coinService->getBalance($sender),
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
