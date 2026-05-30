<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class FounderVerificationService
{
    /**
     * One-time coin bonus for legacy founders.
     */
    protected float $founderBonus;

    /**
     * List of legacy founder emails.
     */
    protected array $legacyEmails = [];

    public function __construct(
        protected CoinService $coinService
    ) {
        $this->legacyEmails = config('zalet.legacy_founder_emails', []);
        $this->founderBonus = config('zalet.economy.founder_bonus', 100.00);
    }

    /**
     * Check if an email is in the legacy founder list.
     */
    public function isLegacyFounder(string $email): bool
    {
        return in_array(strtolower($email), array_map('strtolower', $this->legacyEmails));
    }

    /**
     * Process founder status during user registration.
     * Sets the is_legacy_founder flag and credits one-time bonus.
     * 
     * NOTE: This does NOT change the user's role. Founders are regular users
     * with a boolean flag and a one-time coin bonus. There is NO "founder" role.
     */
    public function processRegistration(User $user): bool
    {
        if (!$this->isLegacyFounder($user->email)) {
            return false;
        }

        return DB::transaction(function () use ($user) {
            // Set the legacy founder flag (NOT a role change!)
            $user->update(['is_legacy_founder' => true]);

            // Credit one-time bonus
            $this->creditFounderBonus($user);

            return true;
        });
    }

    /**
     * Credit the one-time founder bonus to the user's wallet.
     */
    protected function creditFounderBonus(User $user): void
    {
        $wallet = $this->coinService->ensureWallet($user);

        // Create a completed deposit transaction for the bonus
        DB::table('transactions')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'from_wallet_id' => null,
            'to_wallet_id' => $wallet->id,
            'amount' => $this->founderBonus,
            'type' => 'deposit',
            'status' => 'completed',
            'description' => 'Legacy founder welcome bonus',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Credit the wallet
        $wallet->increment('balance', $this->founderBonus);
    }

    /**
     * Manually mark a user as legacy founder (admin action).
     * This also credits the bonus if not already a founder.
     */
    public function manuallyMarkAsFounder(User $user): bool
    {
        if ($user->is_legacy_founder) {
            return false; // Already a founder
        }

        return DB::transaction(function () use ($user) {
            $user->update(['is_legacy_founder' => true]);
            $this->creditFounderBonus($user);
            return true;
        });
    }

    /**
     * Get all legacy founders.
     */
    public function getLegacyFounders()
    {
        return User::where('is_legacy_founder', true)->get();
    }

    /**
     * Get the founder bonus amount.
     */
    public function getFounderBonusAmount(): float
    {
        return $this->founderBonus;
    }
}
