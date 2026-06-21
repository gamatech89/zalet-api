<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Gift;
use App\Models\Media;
use App\Models\StreamSession;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class CoinService
{
    /**
     * Create a wallet for a user if they don't have one.
     */
    public function ensureWallet(User $user): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0]
        );
    }

    /**
     * Deposit coins into a user's wallet (from Raiffeisen payment).
     */
    public function deposit(
        User $user,
        float $amount,
        ?string $raiffeisenOrderId = null
    ): Transaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Deposit amount must be positive.');
        }

        return DB::transaction(function () use ($user, $amount, $raiffeisenOrderId) {
            $wallet = $this->ensureWallet($user);

            // Lock the wallet row for update
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            $transaction = Transaction::create([
                'from_wallet_id' => null, // External source
                'to_wallet_id' => $wallet->id,
                'amount' => $amount,
                'type' => 'deposit',
                'status' => 'pending',
                'raiffeisen_order_id' => $raiffeisenOrderId,
                'description' => 'ZaletCoin purchase',
            ]);

            return $transaction;
        });
    }

    /**
     * Complete a pending deposit (called after payment confirmation).
     */
    public function confirmDeposit(Transaction $transaction): Transaction
    {
        if ($transaction->type !== 'deposit' || $transaction->status !== 'pending') {
            throw new \InvalidArgumentException('Invalid transaction for deposit confirmation.');
        }

        return DB::transaction(function () use ($transaction) {
            $wallet = Wallet::where('id', $transaction->to_wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            $wallet->increment('balance', $transaction->amount);

            $transaction->update(['status' => 'completed']);

            return $transaction->fresh();
        });
    }

    /**
     * Transfer coins between users (tip, subscription, PPV).
     */
    public function transfer(
        User $fromUser,
        User $toUser,
        float $amount,
        string $type,
        ?Media $media = null,
        ?Gift $gift = null,
        ?string $description = null
    ): Transaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Transfer amount must be positive.');
        }

        if (!in_array($type, ['tip', 'subscription', 'ppv'])) {
            throw new \InvalidArgumentException('Invalid transfer type.');
        }

        return DB::transaction(function () use ($fromUser, $toUser, $amount, $type, $media, $gift, $description) {
            $fromWallet = $this->ensureWallet($fromUser);
            $toWallet = $this->ensureWallet($toUser);

            // Lock wallets in consistent order to prevent deadlocks
            $walletIds = [$fromWallet->id, $toWallet->id];
            sort($walletIds);

            Wallet::whereIn('id', $walletIds)->lockForUpdate()->get();

            // Refresh to get locked values
            $fromWallet = $fromWallet->fresh();
            $toWallet = $toWallet->fresh();

            if (!$fromWallet->hasBalance($amount)) {
                throw new \RuntimeException('Insufficient balance.');
            }

            // Gifts: creator gets gift_creator_percent of value (platform keeps the rest)
            // Direct tips: platform takes transfer_fee_percent
            // Other (subscription, ppv): full amount to receiver
            if ($gift) {
                $creatorPercent = AppSetting::get('gift_creator_percent', 50);
                $receiverAmount = round($amount * ($creatorPercent / 100), 2);
            } elseif ($type === 'tip') {
                $feePercent = AppSetting::get('transfer_fee_percent', 10);
                $receiverAmount = round($amount * (1 - $feePercent / 100), 2);
            } else {
                $receiverAmount = $amount;
            }

            // Debit sender full amount
            $fromWallet->decrement('balance', $amount);

            // Credit receiver minus fee (fee stays with platform — not credited anywhere)
            $toWallet->increment('balance', $receiverAmount);

            $transaction = Transaction::create([
                'from_wallet_id' => $fromWallet->id,
                'to_wallet_id'   => $toWallet->id,
                'amount'         => $amount,
                'type'           => $type,
                'status'         => 'completed',
                'media_id'       => $media?->id,
                'gift_id'        => $gift?->id,
                'description'    => $description,
            ]);

            return $transaction;
        });
    }

    /**
     * Send a tip to a user.
     */
    public function tip(User $fromUser, User $toUser, float $amount, ?Gift $gift = null): Transaction
    {
        $description = $gift 
            ? "Tip: {$gift->name}" 
            : 'Direct tip';

        return $this->transfer($fromUser, $toUser, $amount, 'tip', null, $gift, $description);
    }

    /**
     * Send a gift during a live stream.
     */
    public function sendStreamGift(
        User $fromUser,
        User $streamer,
        Gift $gift,
        StreamSession $session
    ): Transaction {
        $transaction = $this->transfer(
            $fromUser,
            $streamer,
            (float) $gift->coin_price,
            'tip',
            null,
            $gift,
            "Stream gift: {$gift->name}"
        );

        $session->addCoins((float) $gift->coin_price);

        return $transaction;
    }

    /**
     * Purchase PPV content.
     */
    public function purchasePpv(User $buyer, Media $media): Transaction
    {
        if (!$media->is_ppv || $media->price_coins === null) {
            throw new \InvalidArgumentException('Media is not available for purchase.');
        }

        $creatorPercent = AppSetting::get('ppv_creator_percent', 60);
        $price = (float) $media->price_coins;
        $creatorAmount = round($price * ($creatorPercent / 100), 2);

        return DB::transaction(function () use ($buyer, $media, $price, $creatorAmount) {
            $buyerWallet   = $this->ensureWallet($buyer);
            $creatorWallet = $this->ensureWallet($media->user);

            $walletIds = [$buyerWallet->id, $creatorWallet->id];
            sort($walletIds);
            Wallet::whereIn('id', $walletIds)->lockForUpdate()->get();

            $buyerWallet   = $buyerWallet->fresh();
            $creatorWallet = $creatorWallet->fresh();

            if (!$buyerWallet->hasBalance($price)) {
                throw new \RuntimeException('Insufficient balance.');
            }

            $buyerWallet->decrement('balance', $price);
            $creatorWallet->increment('balance', $creatorAmount);

            return Transaction::create([
                'from_wallet_id' => $buyerWallet->id,
                'to_wallet_id'   => $creatorWallet->id,
                'amount'         => $price,
                'type'           => 'ppv',
                'status'         => 'completed',
                'media_id'       => $media->id,
                'description'    => "PPV purchase: {$media->title}",
            ]);
        });
    }

    /**
     * Request a withdrawal.
     */
    public function requestWithdrawal(User $user, float $amount): Transaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Withdrawal amount must be positive.');
        }

        return DB::transaction(function () use ($user, $amount) {
            $wallet = $this->ensureWallet($user);
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            if (!$wallet->hasBalance($amount)) {
                throw new \RuntimeException('Insufficient balance for withdrawal.');
            }

            // Debit the wallet immediately (hold funds)
            $wallet->decrement('balance', $amount);

            return Transaction::create([
                'from_wallet_id' => $wallet->id,
                'to_wallet_id' => $wallet->id, // Self-reference for withdrawals
                'amount' => $amount,
                'type' => 'withdrawal',
                'status' => 'pending',
                'description' => 'Withdrawal request',
            ]);
        });
    }

    /**
     * Get wallet balance for a user.
     */
    public function getBalance(User $user): float
    {
        return (float) $this->ensureWallet($user)->balance;
    }

    /**
     * Get transaction history for a user.
     */
    public function getTransactionHistory(User $user, int $limit = 50)
    {
        $wallet = $this->ensureWallet($user);

        return Transaction::where('from_wallet_id', $wallet->id)
            ->orWhere('to_wallet_id', $wallet->id)
            ->with(['gift', 'media'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
