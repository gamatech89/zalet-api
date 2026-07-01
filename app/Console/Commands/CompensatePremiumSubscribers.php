<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\Transaction;
use App\Notifications\PremiumPriceCompensation;
use App\Services\CoinService;
use Illuminate\Console\Command;

class CompensatePremiumSubscribers extends Command
{
    protected $signature = 'subscriptions:compensate-premium
                            {--dry-run : Preview affected users without making changes}
                            {--coins=1000 : Number of coins to credit per user}
                            {--old-price=2000 : The old price in RSD to match against}';

    protected $description = 'Credit coins and send email to Premium subscribers who paid the old price';

    public function __construct(private CoinService $coinService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $coins    = (int) $this->option('coins');
        $oldPrice = (float) $this->option('old-price');
        $isDry    = $this->option('dry-run');
        $desc     = "Nadoknada – sniženje Premium pretplate na 750 RSD";

        // Find Premium subscriptions (plan level = 1) paid at the old price
        $subscriptions = Subscription::with('user.wallet')
            ->whereHas('plan', fn ($q) => $q->where('level', 1))
            ->where('price_paid', $oldPrice)
            ->whereHas('user')
            ->get();

        // Deduplicate — one compensation per user even if they renewed multiple times at old price
        $users = $subscriptions->pluck('user')->unique('id')->filter();

        if ($users->isEmpty()) {
            $this->info("No Premium subscribers found who paid {$oldPrice} RSD.");
            return self::SUCCESS;
        }

        // Exclude already-compensated users (idempotency)
        $alreadyDone = Transaction::where('description', $desc)
            ->whereNotNull('to_wallet_id')
            ->pluck('to_wallet_id')
            ->toArray();

        $pending = $users->filter(function ($user) use ($alreadyDone) {
            $walletId = $user->wallet?->id;
            return $walletId && !in_array($walletId, $alreadyDone);
        });

        $this->info("Premium subscribers who paid {$oldPrice} RSD: " . $users->count());
        $this->info("Already compensated: " . ($users->count() - $pending->count()));
        $this->info("Pending: " . $pending->count());
        $this->newLine();

        if ($pending->isEmpty()) {
            $this->info('All eligible users have already been compensated.');
            return self::SUCCESS;
        }

        $this->table(
            ['#', 'Username', 'Email', 'Coins', 'Status'],
            $pending->values()->map(fn ($u, $i) => [
                $i + 1,
                $u->username,
                $u->email,
                "+{$coins} ZC",
                $isDry ? 'DRY RUN' : 'pending',
            ])->toArray()
        );

        if ($isDry) {
            $this->warn('Dry run — no changes made.');
            return self::SUCCESS;
        }

        if (!$this->confirm("Credit {$coins} ZC and send email to {$pending->count()} users?")) {
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($pending->count());
        $bar->start();

        $credited = 0;
        $failed   = 0;

        foreach ($pending as $user) {
            try {
                $this->coinService->credit($user, (float) $coins, $desc);
                $user->notify(new PremiumPriceCompensation($coins));
                $credited++;
            } catch (\Exception $e) {
                $failed++;
                $this->newLine();
                $this->error("Failed for {$user->email}: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Credited: {$credited}, Failed: {$failed}.");

        return self::SUCCESS;
    }
}
