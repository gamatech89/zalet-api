<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    /**
     * List the user's bank accounts.
     *
     * GET /api/v1/bank-accounts
     */
    public function index(Request $request): JsonResponse
    {
        $accounts = BankAccount::where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($a) => $this->formatAccount($a));

        return response()->json(['data' => $accounts]);
    }

    /**
     * Add a new bank account.
     *
     * POST /api/v1/bank-accounts
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'min:10', 'max:30'],
            'label' => ['nullable', 'string', 'max:100'],
            'set_as_default' => ['boolean'],
        ]);

        $user = $request->user();

        // Check max limit (5 per user)
        $count = BankAccount::where('user_id', $user->id)->count();
        if ($count >= 5) {
            return response()->json([
                'message' => 'Maximum of 5 bank accounts allowed.',
            ], 422);
        }

        $isFirst = $count === 0;
        $accountNumber = $validated['account_number'];

        $account = BankAccount::create([
            'user_id' => $user->id,
            'bank_name' => $validated['bank_name'],
            'account_number' => $accountNumber,
            'last_four' => substr($accountNumber, -4),
            'label' => $validated['label'] ?? null,
            'is_default' => $isFirst,
        ]);

        if ($isFirst || ($validated['set_as_default'] ?? false)) {
            $account->makeDefault();
        }

        return response()->json([
            'message' => 'Bank account added.',
            'data' => $this->formatAccount($account),
        ], 201);
    }

    /**
     * Set a bank account as the default.
     *
     * PUT /api/v1/bank-accounts/{bankAccount}/default
     */
    public function setDefault(Request $request, BankAccount $bankAccount): JsonResponse
    {
        if ($bankAccount->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $bankAccount->makeDefault();

        return response()->json([
            'message' => 'Default bank account updated.',
            'data' => $this->formatAccount($bankAccount->fresh()),
        ]);
    }

    /**
     * Delete a bank account.
     *
     * DELETE /api/v1/bank-accounts/{bankAccount}
     */
    public function destroy(Request $request, BankAccount $bankAccount): JsonResponse
    {
        if ($bankAccount->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $wasDefault = $bankAccount->is_default;
        $bankAccount->delete();

        if ($wasDefault) {
            $next = BankAccount::where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->first();

            $next?->makeDefault();
        }

        return response()->json([
            'message' => 'Bank account removed.',
        ]);
    }

    protected function formatAccount(BankAccount $account): array
    {
        return [
            'id' => $account->id,
            'bank_name' => $account->bank_name,
            'last_four' => $account->last_four,
            'is_default' => $account->is_default,
            'label' => $account->label,
            'display_name' => $account->displayName(),
            'created_at' => $account->created_at->toIso8601String(),
        ];
    }
}
