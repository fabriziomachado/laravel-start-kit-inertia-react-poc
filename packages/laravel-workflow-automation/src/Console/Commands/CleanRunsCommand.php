<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Console\Commands;

use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use Illuminate\Console\Command;

final class CleanRunsCommand extends Command
{
    protected $signature = 'workflow:clean-runs {--days= : Override retention days from config}';

    protected $description = 'Delete workflow runs older than the configured retention period.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('workflow-automation.log_retention_days', 30));

        if ($days <= 0) {
            $this->components->info('Log retention is disabled (0 days). Nothing to clean.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);

        $deleted = WorkflowRun::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->components->info("Deleted {$deleted} workflow run(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
