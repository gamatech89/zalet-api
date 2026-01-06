<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Wallet\Models\LedgerEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class GetTransactionHistoryAction
{
    /**
     * Get paginated transaction history for user's wallet.
     *
     * @param string|null $type Filter by transaction type
     * @return LengthAwarePaginator<int, LedgerEntry>
     */
    public function execute(
        User $user,
        ?string $type = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $wallet = $user->wallet;

        if ($wallet === null) {
            // Return empty paginator if no wallet exists
            return LedgerEntry::query()
                ->where('wallet_id', 0) // Will never match
                ->paginate($perPage);
        }

        $query = LedgerEntry::query()
            ->where('wallet_id', $wallet->id)
            ->orderByDesc('created_at');

        if ($type !== null && in_array($type, LedgerEntry::getTypes(), true)) {
            $query->where('type', $type);
        }

        return $query->paginate($perPage);
    }
}
