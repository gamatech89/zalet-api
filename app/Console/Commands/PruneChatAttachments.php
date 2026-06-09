<?php

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneChatAttachments extends Command
{
    protected $signature = 'chat:prune-attachments {--dry-run : List what would be deleted without deleting}';

    protected $description = 'Delete S3 attachment files from old messages (keeps message text). Groups: 90 days, DMs: 365 days.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $total = 0;

        // Group chat attachments older than 90 days
        $total += $this->prune(
            isGroup: true,
            days: 90,
            dryRun: $dryRun,
        );

        // DM attachments older than 365 days
        $total += $this->prune(
            isGroup: false,
            days: 365,
            dryRun: $dryRun,
        );

        $verb = $dryRun ? 'Would delete' : 'Deleted';
        $this->info("{$verb} attachments from {$total} messages.");

        return Command::SUCCESS;
    }

    private function prune(bool $isGroup, int $days, bool $dryRun): int
    {
        $cutoff = now()->subDays($days);
        $label = $isGroup ? 'group' : 'DM';

        $query = Message::query()
            ->whereNotNull('media_url')
            ->where('created_at', '<', $cutoff)
            ->whereHas('conversation', fn ($q) => $q->where('is_group', $isGroup));

        $count = $query->count();

        if ($count === 0) {
            $this->line("  No {$label} attachments to prune.");
            return 0;
        }

        $this->line("  Found {$count} {$label} attachment(s) older than {$days} days.");

        if ($dryRun) {
            return $count;
        }

        $deleted = 0;

        $query->chunkById(200, function ($messages) use (&$deleted) {
            $paths = [];

            foreach ($messages as $message) {
                $path = $this->s3PathFromUrl($message->media_url);
                if ($path) {
                    $paths[] = $path;
                }
            }

            if (!empty($paths)) {
                Storage::disk('s3')->delete($paths);
            }

            // Null out media_url; keep the message row
            Message::whereIn('id', $messages->pluck('id'))
                ->update(['media_url' => null, 'media_type' => null]);

            $deleted += $messages->count();
        });

        return $deleted;
    }

    /**
     * Extract the relative S3 path from a full URL.
     * Handles both virtual-hosted (bucket.s3.region.amazonaws.com/path)
     * and path-style (s3.amazonaws.com/bucket/path) URLs.
     */
    private function s3PathFromUrl(string $url): ?string
    {
        // Fast path: all chat media lives under chat-media/
        if (preg_match('#(chat-media/.+)$#', $url, $m)) {
            return $m[1];
        }

        return null;
    }
}
