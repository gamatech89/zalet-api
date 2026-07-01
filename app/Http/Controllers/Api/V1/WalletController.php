<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DepositRequest;
use App\Http\Requests\TransferRequest;
use App\Http\Requests\WithdrawRequest;
use App\Models\AppSetting;
use App\Models\BankAccount;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CoinService;
use App\Services\RaiffeisenPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    public function __construct(
        protected CoinService $coinService,
    ) {}

    /**
     * Get the authenticated user's wallet balance.
     * 
     * GET /api/v1/wallet
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallet = $this->coinService->ensureWallet($user);

        return response()->json([
            'data' => [
                'id' => $wallet->id,
                'balance' => $wallet->balance,
                'currency' => 'ZLC', // ZaletCoin
                'updated_at' => $wallet->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get the authenticated user's transaction history.
     * 
     * GET /api/v1/wallet/transactions
     */
    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallet = $this->coinService->ensureWallet($user);

        $walletId = $wallet->id;

        $query = \App\Models\Transaction::where(function ($q) use ($walletId) {
                $q->where('to_wallet_id', $walletId)
                  ->orWhere('from_wallet_id', $walletId);
            })
            ->with(['gift', 'fromWallet.user:id,username', 'toWallet.user:id,username'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $transactions = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    /**
     * Get a single transaction detail (receipt).
     * 
     * GET /api/v1/wallet/transactions/{transaction}
     */
    public function showTransaction(Request $request, Transaction $transaction): JsonResponse
    {
        $user = $request->user();
        $wallet = $this->coinService->ensureWallet($user);

        // Ensure user owns this transaction
        if ($transaction->from_wallet_id !== $wallet->id && $transaction->to_wallet_id !== $wallet->id) {
            return response()->json(['message' => 'Transaction not found.'], 404);
        }

        $transaction->load(['gift', 'media', 'fromWallet.user:id,username', 'toWallet.user:id,username']);

        return response()->json([
            'data' => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'status' => $transaction->status,
                'description' => $transaction->description,
                'raiffeisen_order_id' => $transaction->raiffeisen_order_id,
                'gift' => $transaction->gift ? [
                    'name' => $transaction->gift->name,
                    'icon' => $transaction->gift->icon_url ?? null,
                ] : null,
                'media' => $transaction->media ? [
                    'id' => $transaction->media->id,
                    'title' => $transaction->media->title,
                ] : null,
                'from_user' => $transaction->fromWallet?->user ? [
                    'id' => $transaction->fromWallet->user->id,
                    'username' => $transaction->fromWallet->user->username,
                ] : null,
                'to_user' => $transaction->toWallet?->user ? [
                    'id' => $transaction->toWallet->user->id,
                    'username' => $transaction->toWallet->user->username,
                ] : null,
                'created_at' => $transaction->created_at->toIso8601String(),
                'updated_at' => $transaction->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Transfer coins to another user.
     * 
     * POST /api/v1/wallet/transfer
     */
    public function transfer(TransferRequest $request): JsonResponse
    {
        $sender = $request->user();
        $recipient = User::findOrFail($request->validated('recipient_id'));

        try {
            $transaction = $this->coinService->tip(
                fromUser: $sender,
                toUser: $recipient,
                amount: $request->validated('amount'),
            );

            return response()->json([
                'message' => 'Transfer successful.',
                'data' => [
                    'transaction_id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'recipient' => [
                        'id' => $recipient->id,
                        'username' => $recipient->username,
                    ],
                    'new_balance' => $this->coinService->getBalance($sender),
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Initiate a deposit (purchase ZaletCoins).
     * 
     * POST /api/v1/wallet/deposit
     * 
     * Accepts optional payment_method_id for tokenized payments.
     * If no payment method is specified, redirects to Raiffeisen gateway.
     */
    public function deposit(DepositRequest $request, RaiffeisenPaymentService $paymentService): JsonResponse
    {
        $user = $request->user();
        $amount = $request->validated('amount');
        $paymentMethodId = $request->input('payment_method_id');

        // Allow mock deposits in dev environment
        if ($request->input('payment_method') === 'mock_provider' && app()->isLocal()) {
            $tx = $this->coinService->deposit($user, (float)$amount, 'MOCK-' . time());
            $this->coinService->confirmDeposit($tx);
            
            return response()->json([
                'message' => 'Mock deposit successful',
                'data' => [
                    'id' => $tx->id,
                    'amount' => $tx->amount,
                    'new_balance' => $this->coinService->getBalance($user)
                ]
            ]);
        }

        try {
            // If a saved payment method is provided, use tokenized payment
            // Tokenized (payByToken) only works for amounts up to 2400 RSD — fall back to redirect above that
            $useTokenized = $paymentMethodId && $amount <= 2400;

            if ($useTokenized) {
                $paymentMethod = PaymentMethod::where('id', $paymentMethodId)
                    ->where('user_id', $user->id)
                    ->firstOrFail();

                if ($paymentMethod->isExpired()) {
                    return response()->json([
                        'message' => 'This card has expired. Please use a different payment method.',
                    ], 422);
                }

                $paymentData = $paymentService->createTokenizedPayment($user, $amount, $paymentMethod);

                return response()->json([
                    'message' => 'Payment initiated with saved card.',
                    'data' => [
                        'order_id' => $paymentData['order_id'],
                        'amount' => $amount,
                        'currency' => 'RSD',
                        'payment_method' => [
                            'card_brand' => $paymentMethod->card_brand,
                            'last_four' => $paymentMethod->last_four,
                        ],
                        'status' => 'processing',
                    ],
                ]);
            }

            // No saved card — redirect to Raiffeisen hosted payment page
            $paymentData = $paymentService->createPaymentOrder($user, $amount);

            return response()->json([
                'message' => 'Payment order created. Redirect user to payment URL.',
                'data' => [
                    'payment_url' => $paymentData['payment_url'],
                    'order_id' => $paymentData['order_id'],
                    'amount' => $amount,
                    'currency' => 'RSD',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Payment order creation failed', [
                'user_id' => $user->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to create payment order.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Request a withdrawal.
     * 
     * POST /api/v1/wallet/withdraw
     * 
     * Accepts optional bank_account_id. Returns fee calculation.
     */
    public function withdraw(WithdrawRequest $request): JsonResponse
    {
        $user = $request->user();
        $amount = $request->validated('amount');
        $bankAccountId = $request->input('bank_account_id');

        // Validate bank account ownership if provided
        $bankAccount = null;
        if ($bankAccountId) {
            $bankAccount = BankAccount::where('id', $bankAccountId)
                ->where('user_id', $user->id)
                ->first();

            if (!$bankAccount) {
                return response()->json([
                    'message' => 'Bank account not found.',
                ], 404);
            }
        }

        // Calculate fee
        $feePercent = AppSetting::get('withdrawal_fee_percent', 2);
        $feeAmount = round($amount * ($feePercent / 100), 2);
        $netAmount = $amount - $feeAmount;
        $exchangeRate = AppSetting::get('coin_to_rsd_rate', 1.2);
        $estimatedRsd = round($netAmount * $exchangeRate, 2);

        try {
            $transaction = $this->coinService->requestWithdrawal($user, $amount);

            return response()->json([
                'message' => 'Withdrawal request submitted. Pending admin approval.',
                'data' => [
                    'transaction_id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'fee_percent' => $feePercent,
                    'fee_amount' => $feeAmount,
                    'net_amount' => $netAmount,
                    'exchange_rate' => $exchangeRate,
                    'estimated_rsd' => $estimatedRsd,
                    'processing_days' => config('zalet.withdrawal.processing_days', '1-2'),
                    'bank_account' => $bankAccount ? [
                        'bank_name' => $bankAccount->bank_name,
                        'last_four' => $bankAccount->last_four,
                    ] : null,
                    'status' => $transaction->status,
                    'new_balance' => $this->coinService->getBalance($user),
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get withdrawal fee calculation (preview).
     * 
     * GET /api/v1/wallet/withdrawal-preview
     */
    public function withdrawalPreview(Request $request): JsonResponse
    {
        $amount = (float) $request->query('amount', 0);
        
        if ($amount <= 0) {
            return response()->json(['message' => 'Amount must be positive.'], 422);
        }

        $feePercent = AppSetting::get('withdrawal_fee_percent', 2);
        $feeAmount = round($amount * ($feePercent / 100), 2);
        $netAmount = $amount - $feeAmount;
        $exchangeRate = AppSetting::get('coin_to_rsd_rate', 1.2);
        $estimatedRsd = round($netAmount * $exchangeRate, 2);

        return response()->json([
            'data' => [
                'amount' => $amount,
                'fee_percent' => $feePercent,
                'fee_amount' => $feeAmount,
                'net_amount' => $netAmount,
                'exchange_rate' => $exchangeRate,
                'estimated_rsd' => $estimatedRsd,
                'processing_days' => config('zalet.withdrawal.processing_days', '1-2'),
            ],
        ]);
    }
}
