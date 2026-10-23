<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Deletes read notifications past a certain age; unread ones are kept.
 */
#[Description('Delete old read notifications')]
final class PruneNotifications extends Command
{
    protected $signature = 'forum:prune-notifications {--days=90 : Age in days after which read notifications are deleted}';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $deleted = DatabaseNotification::query()
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->components->info("Deleted {$deleted} read notifications older than {$days} days.");

        return self::SUCCESS;
    }
}
