<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\MediaPurchase;
use App\Services\CoinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaPurchaseController extends Controller
{
    public function __construct(
        protected CoinService $coinService
    ) {}

    /**
     * Purchase PPV content.
     */
    public function store(Request $request, Media $media): JsonResponse
    {
        $user = $request->user();

        // Check if media is PPV
        if (!$media->is_ppv || $media->price_coins === null) {
            return response()->json([
                'message' => 'This content is not available for purchase.',
            ], 400);
        }

        // Check if user is the owner
        if ($media->user_id === $user->id) {
            return response()->json([
                'message' => 'You cannot purchase your own content.',
            ], 400);
        }

        // Check if already purchased
        $existingPurchase = MediaPurchase::where('user_id', $user->id)
            ->where('media_id', $media->id)
            ->first();

        if ($existingPurchase) {
            return response()->json([
                'message' => 'You have already purchased this content.',
            ], 400);
        }

        try {
            // Use existing CoinService purchasePpv method
            $transaction = $this->coinService->purchasePpv($user, $media);

            // Create purchase record
            MediaPurchase::create([
                'user_id' => $user->id,
                'media_id' => $media->id,
                'transaction_id' => $transaction->id,
            ]);

            return response()->json([
                'message' => 'Content purchased successfully',
                'data' => [
                    'media_id' => $media->id,
                    'title' => $media->title,
                    'amount_paid' => $media->price_coins,
                    'transaction_id' => $transaction->id,
                    'new_balance' => $this->coinService->getBalance($user),
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
