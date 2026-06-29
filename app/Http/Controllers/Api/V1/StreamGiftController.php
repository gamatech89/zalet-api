<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\GiftSentEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendStreamGiftRequest;
use App\Models\Gift;
use App\Models\LiveStream;
use App\Services\CoinService;
use App\Services\LiveStreamService;
use Illuminate\Http\JsonResponse;

class StreamGiftController extends Controller
{
    public function __construct(
        private CoinService $coinService,
        private LiveStreamService $liveStreamService,
    ) {}

    /**
     * Send a gift to a live stream.
     * POST /api/v1/streams/{liveStream}/gift
     */
    public function store(SendStreamGiftRequest $request, LiveStream $liveStream): JsonResponse
    {
        // Check if stream is live
        if (!$liveStream->is_live) {
            return response()->json([
                'message' => 'This stream is not currently live.',
            ], 422);
        }

        $sender = $request->user();
        $streamer = $liveStream->user;
        $gift = Gift::findOrFail($request->gift_id);
        $session = $liveStream->currentSession;

        if (!$session) {
            return response()->json([
                'message' => 'No active session for this stream.',
            ], 422);
        }

        // Cannot gift yourself
        if ($sender->id === $streamer->id) {
            return response()->json([
                'message' => 'You cannot send gifts to yourself.',
            ], 422);
        }

        try {
            // Process the gift transaction (uses CoinService)
            $transaction = $this->coinService->sendStreamGift(
                $sender,
                $streamer,
                $gift,
                $session
            );

            // Broadcast the gift event to the stream channel
            broadcast(new GiftSentEvent($sender, $streamer, $gift, $session));

            // Update stream goals via service
            $this->liveStreamService->updateGoalProgress($liveStream, (int) $gift->coin_price);

            return response()->json([
                'message' => 'Gift sent successfully!',
                'data' => [
                    'gift' => [
                        'id' => $gift->id,
                        'name' => $gift->name,
                        'coin_price' => (float) $gift->coin_price,
                    ],
                    'transaction_id' => $transaction->id,
                    'session_total' => (float) $session->fresh()->total_coins_collected,
                ],
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
