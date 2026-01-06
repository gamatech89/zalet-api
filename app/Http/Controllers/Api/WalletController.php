<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\Identity\Models\User;
use App\Domains\Wallet\Actions\GetTransactionHistoryAction;
use App\Domains\Wallet\Actions\GetWalletAction;
use App\Domains\Wallet\Actions\TransferCreditsAction;
use App\Domains\Wallet\Models\LedgerEntry;
use App\Domains\Wallet\Resources\LedgerEntryResource;
use App\Domains\Wallet\Resources\WalletResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WalletController extends Controller
{
    public function __construct(
        private readonly GetWalletAction $getWallet,
    ) {}

    /**
     * Get current user's wallet.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $includeRecent = $request->boolean('include_recent', true);
        $wallet = $this->getWallet->execute(
            user: $user,
            withRecentTransactions: $includeRecent,
            recentLimit: 5,
        );

        return response()->json([
            'data' => new WalletResource($wallet),
        ]);
    }

    /**
     * Get transaction history.
     */
    public function transactions(
        Request $request,
        GetTransactionHistoryAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $type = $request->query('type');
        $type = is_string($type) ? $type : null;

        $perPage = min((int) $request->query('per_page', 20), 100);

        $transactions = $action->execute(
            user: $user,
            type: $type,
            perPage: $perPage,
        );

        return response()->json([
            'data' => LedgerEntryResource::collection($transactions->items()),
            'meta' => [
                'currentPage' => $transactions->currentPage(),
                'lastPage' => $transactions->lastPage(),
                'perPage' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    /**
     * Transfer credits to another user.
     */
    public function transfer(
        Request $request,
        TransferCreditsAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'recipient_uuid' => ['required', 'uuid', 'exists:users,uuid'],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $recipient = User::where('uuid', $validated['recipient_uuid'])->firstOrFail();

        try {
            $result = $action->execute(
                sender: $user,
                recipient: $recipient,
                amount: (int) $validated['amount'],
                description: $validated['description'] ?? null,
            );

            // Refresh sender's wallet to get updated balance
            $wallet = $this->getWallet->execute($user, withRecentTransactions: true);

            return response()->json([
                'data' => [
                    'wallet' => new WalletResource($wallet),
                    'transaction' => new LedgerEntryResource($result['sender_entry']),
                ],
                'message' => 'Credits transferred successfully.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get available transaction types.
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'data' => LedgerEntry::getTypes(),
        ]);
    }
}
