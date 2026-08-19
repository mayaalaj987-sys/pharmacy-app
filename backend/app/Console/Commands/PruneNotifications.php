<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

/**
 * Clears out notifications nobody is going to read again.
 *
 * Three days of testing produced 132 rows. A working pharmacy generates far
 * more, forever, and nothing ever deleted one — so the table grows without
 * limit and the bell gets slower every month it runs.
 *
 * Only the read ones go. An unread notification is still asking for something,
 * however old it is: a delivery nobody confirmed, stock nobody wrote off. The
 * one thing worse than a cluttered bell is one that quietly drops the message
 * that mattered.
 */
class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune
        {--days=30 : How long a read notification is kept}
        {--dry-run : Report what would go without deleting anything}';

    protected $description = 'Delete notifications that have been read and are older than the retention window.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $stale = Notification::where('is_read', true)
            ->where('created_at', '<', $cutoff);

        $count = (clone $stale)->count();

        if (! $this->option('dry-run')) {
            $stale->delete();
        }

        $this->info(sprintf(
            '%s %d read notification(s) older than %d day(s). %d unread kept regardless of age.',
            $this->option('dry-run') ? 'Would delete' : 'Deleted',
            $count,
            $days,
            Notification::where('is_read', false)->where('created_at', '<', $cutoff)->count(),
        ));

        return self::SUCCESS;
    }
}
