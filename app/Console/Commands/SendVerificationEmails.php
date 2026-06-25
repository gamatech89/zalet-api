<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SendVerificationEmails extends Command
{
    protected $signature = 'users:send-verification
                            {--dry-run : Preview unverified users without sending emails}
                            {--batch-size=50 : Number of emails to send per batch}
                            {--delay=2 : Seconds to wait between batches}';

    protected $description = 'Send email verification to all unverified users';

    public function handle(): int
    {
        $users = User::whereNull('email_verified_at')->get();
        $total = $users->count();

        if ($total === 0) {
            $this->info('All users are already verified.');
            return self::SUCCESS;
        }

        $this->info("Found {$total} unverified users.");

        if ($this->option('dry-run')) {
            $this->table(
                ['Email', 'Username', 'Registered'],
                $users->map(fn ($u) => [
                    $u->email,
                    $u->username ?? '—',
                    $u->created_at->format('Y-m-d'),
                ])->toArray()
            );
            return self::SUCCESS;
        }

        if (!$this->confirm("Send verification emails to {$total} users?")) {
            return self::SUCCESS;
        }

        $batchSize = (int) $this->option('batch-size');
        $delay = (int) $this->option('delay');
        $sent = 0;
        $failed = 0;
        $batchNum = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($users->chunk($batchSize) as $chunk) {
            $batchNum++;

            foreach ($chunk as $user) {
                try {
                    $user->sendEmailVerificationNotification();
                    $sent++;
                } catch (\Exception $e) {
                    $failed++;
                    $this->newLine();
                    $this->warn("Failed for {$user->email}: " . $e->getMessage());
                }
                $bar->advance();
            }

            // Rate-limit pause between batches (skip after last batch)
            if ($batchNum * $batchSize < $total && $delay > 0) {
                sleep($delay);
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. Sent: {$sent}, Failed: {$failed}.");

        return self::SUCCESS;
    }
}
