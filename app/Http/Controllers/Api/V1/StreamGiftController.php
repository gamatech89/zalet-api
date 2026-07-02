<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\GiftSentEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendStreamGiftRequest;
use App\Models\Gift;
use App\Models\LiveStream;
use App\Models\Transaction;
use App\Services\CoinService;
use App\Services\LiveStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    /**
     * Top gifters for the stream's current (or latest) session.
     * GET /api/v1/streams/{liveStream}/top-gifters  (public)
     */
    public function topGifters(Request $request, LiveStream $liveStream): JsonResponse
    {
        $session = $liveStream->currentSession
            ?? $liveStream->sessions()->latest('created_at')->first();

        if (!$session) {
            return response()->json(['data' => [], 'my_total' => null, 'my_rank' => null]);
        }

        $base = Transaction::query()
            ->where('stream_session_id', $session->id)
            ->where('type', 'tip')
            ->whereNotNull('gift_id');

        $rows = (clone $base)
            ->join('wallets', 'wallets.id', '=', 'transactions.from_wallet_id')
            ->join('users', 'users.id', '=', 'wallets.user_id')
            ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
            ->groupBy('users.id', 'users.username', 'profiles.avatar_url')
            ->orderByRaw('SUM(transactions.amount) DESC')
            ->limit(10)
            ->get([
                DB::raw('users.id as user_id'),
                'users.username',
                'profiles.avatar_url',
                DB::raw('SUM(transactions.amount) as total_coins'),
            ]);

        $myTotal = null;
        $myRank  = null;
        $user    = $request->user() ?? auth('sanctum')->user();
        if ($user && $user->wallet) {
            $myTotal = (float) (clone $base)->where('from_wallet_id', $user->wallet->id)->sum('amount');
            if ($myTotal > 0) {
                $myRank = 1 + (clone $base)
                    ->select('from_wallet_id')
                    ->groupBy('from_wallet_id')
                    ->havingRaw('SUM(amount) > ?', [$myTotal])
                    ->get()->count();
            } else {
                $myTotal = null;
            }
        }

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'user_id'     => $r->user_id,
                'username'    => $r->username,
                'avatar_url'  => $r->avatar_url,
                'total_coins' => (float) $r->total_coins,
            ]),
            'my_total' => $myTotal,
            'my_rank'  => $myRank,
        ]);
    }
}
