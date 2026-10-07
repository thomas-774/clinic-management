<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deletes audit log rows older than `clinic.audit_retention_years` (NFR-S.5).
 * The only way rows leave the table: a query, never the AuditLog model, which
 * refuses deletes. Scheduled daily (routes/console.php).
 */
class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune';

    protected $description = 'Delete audit log rows older than the retention period (clinic.audit_retention_years)';

    public function handle(): int
    {
        $years = (int) config('clinic.audit_retention_years');
        $cutoff = now()->subYears($years);

        $deleted = 0;
        do {
            // In batches, so a first run over years of rows never holds a long lock.
            $batch = DB::table('audit_logs')->where('created_at', '<', $cutoff)->orderBy('id')->limit(1000)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Deleted {$deleted} audit log rows older than {$cutoff->toDateString()} ({$years} years).");

        return self::SUCCESS;
    }
}
