<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\Identity\Models\User;
use App\Domains\Wallet\Actions\GetCreatorEarningsAction;
use App\Domains\Wallet\Actions\GetGiftCatalogAction;
use App\Domains\Wallet\Actions\GetWalletAction;
use App\Domains\Wallet\Actions\SendGiftAction;
use App\Domains\Wallet\Resources\CreatorEarningsResource;
use App\Domains\Wallet\Resources\GiftResource;
use App\Domains\Wallet\Resources\GiftTransactionResource;
use App\Domains\Wallet\Resources\WalletResource;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GiftController extends Controller
{
    public function __construct(
        private readonly GetGiftCatalogAction $getGiftCatalog,
        private readonly SendGiftAction $sendGift,
        private readonly GetCreatorEarningsAction $getCreatorEarnings,
        private readonly GetWalletAction $getWallet,
    ) {}

    /**
     * Get all available gifts.
     */
    public function catalog(): JsonResponse
    {
        $gifts = $this->getGiftCatalog->execute();

        $resources = [];
        foreach ($gifts as $id => $gift) {
            $resources[] = GiftResource::fromCatalog($id, $gift);
        }

        return response()->json([
            'data' => $resources,
        ]);
    }

    /**
     * Send a gift to another user.
     */
    public function send(Request $request): JsonResponse
    {
        /** @var User $sender */
        $sender = $request->user();

        $validated = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'gift_type' => ['required', 'string', 'max:50'],
            'live_session_id' => ['nullable', 'integer'],
        ]);

        /** @var User $recipient */
        $recipient = User::findOrFail($validated['recipient_id']);
        $liveSessionId = isset($validated['live_session_id'])
            ? (int) $validated['live_session_id']
            : null;

        try {
            $result = $this->sendGift->execute(
                sender: $sender,
                recipient: $recipient,
                giftType: $validated['gift_type'],
                liveSessionId: $liveSessionId,
            );

            // Refresh sender's wallet to get updated balance
            $wallet = $this->getWallet->execute($sender, withRecentTransactions: true);

            return response()->json([
                'data' => [
                    'wallet' => new WalletResource($wallet),
                    'transaction' => new GiftTransactionResource($result['sender_entry']),
                    'gift' => [
                        'type' => $validated['gift_type'],
                        'name' => $result['gift']['name'],
                        'credits' => $result['gift']['credits'],
                        'icon' => $result['gift']['icon'],
                        'animation' => $result['gift']['animation'],
                    ],
                ],
                'message' => 'Gift sent successfully.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get creator earnings summary.
     */
    public function earnings(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $startDateParsed = is_string($startDate) ? Carbon::parse($startDate) : null;
        $endDateParsed = is_string($endDate) ? Carbon::parse($endDate) : null;

        $earnings = $this->getCreatorEarnings->execute(
            user: $user,
            startDate: $startDateParsed,
            endDate: $endDateParsed,
        );

        return response()->json([
            'data' => new CreatorEarningsResource($earnings),
        ]);
    }

    /**
     * Get detailed earnings breakdown by gift type.
     */
    public function earningsBreakdown(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $startDateParsed = is_string($startDate) ? Carbon::parse($startDate) : null;
        $endDateParsed = is_string($endDate) ? Carbon::parse($endDate) : null;

        $breakdown = $this->getCreatorEarnings->getBreakdown(
            user: $user,
            startDate: $startDateParsed,
            endDate: $endDateParsed,
        );

        // Transform to camelCase
        $transformedBreakdown = [];
        foreach ($breakdown as $giftType => $data) {
            $transformedBreakdown[$giftType] = [
                'giftType' => $data['gift_type'],
                'giftName' => $data['gift_name'],
                'count' => $data['count'],
                'totalCredits' => $data['total_credits'],
            ];
        }

        return response()->json([
            'data' => $transformedBreakdown,
        ]);
    }
}
