<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\GiftSentEvent;
use App\Events\StreamGoalUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendStreamGiftRequest;
use App\Models\Gift;
use App\Models\LiveStream;
use App\Enums\EventType;
use App\Models\UserEvent;
use App\Services\Achievements\Payloads\GiftSentPayload;
use App\Services\CoinService;
use Illuminate\Http\JsonResponse;

class StreamGiftController extends Controller
{
    public function __construct(
        private CoinService $coinService
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
            broadcast(new GiftSentEvent($sender, $streamer, $gift, $session))->toOthers();

            UserEvent::record($sender, EventType::GIFT_SENT, new GiftSentPayload(
                giftId: $gift->id,
                recipientId: $streamer->id,
                coinPrice: (float) $gift->coin_price,
            ));

            // Update stream goals if any are set
            $goals = $liveStream->goals ?? [];
            if (!empty($goals)) {
                $coinAmount = (int) $gift->coin_price;
                foreach ($goals as $idx => $goal) {
                    if ($goal['current_coins'] < $goal['target_coins']) {
                        $wasDone = false;
                        $goals[$idx]['current_coins'] = min(
                            $goals[$idx]['current_coins'] + $coinAmount,
                            $goals[$idx]['target_coins']
                        );
                        $isNowDone = $goals[$idx]['current_coins'] >= $goals[$idx]['target_coins'];
                        $liveStream->update(['goals' => $goals]);
                        broadcast(new StreamGoalUpdatedEvent($liveStream->fresh(), $idx, !$wasDone && $isNowDone));
                        break; // Fill one goal at a time
                    }
                }
            }

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
