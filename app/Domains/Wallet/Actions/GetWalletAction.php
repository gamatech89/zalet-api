<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Wallet\Models\Wallet;

final class GetWalletAction
{
    /**
     * Get or create wallet for user.
     *
     * @param bool $withRecentTransactions Whether to load recent transactions
     * @param int $recentLimit Number of recent transactions to load
     */
    public function execute(
        User $user,
        bool $withRecentTransactions = false,
        int $recentLimit = 5,
    ): Wallet {
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'currency' => 'CREDITS']
        );

        if ($withRecentTransactions) {
            $wallet->load(['ledgerEntries' => function ($query) use ($recentLimit): void {
                $query->latest('created_at')->limit($recentLimit);
            }]);
        }

        return $wallet;
    }
}
