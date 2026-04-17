<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Console\Commands;

use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Jobs\ExecuteWorkflowJob;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class ScheduleRunCommand extends Command
{
    protected $signature = 'workflow:schedule-run';

    protected $description = 'Check and dispatch due scheduled workflow triggers.';

    public function handle(): int
    {
        $triggers = WorkflowNode::query()
            ->where('type', NodeType::Trigger)
            ->where('node_key', 'schedule')
            ->whereHas('workflow', fn ($q) => $q->where('is_active', true))
            ->get();

        $dispatched = 0;

        foreach ($triggers as $node) {
            if ($this->isDue($node->config ?? [])) {
                ExecuteWorkflowJob::dispatch(
                    workflowId: $node->workflow_id,
                    payload: [['triggered_at' => now()->toISOString()]],
                    triggerNodeId: $node->id,
                )->onQueue(config('workflow-automation.queue', 'default'));

                $dispatched++;

                $this->components->info("Dispatched workflow #{$node->workflow_id} (schedule trigger)");
            }
        }

        $this->components->info("Done. Dispatched {$dispatched} workflow(s).");

        return self::SUCCESS;
    }

    private function isDue(array $config): bool
    {
        $type = $config['interval_type'] ?? 'custom_cron';

        if ($type === 'custom_cron') {
            $cron = $config['cron'] ?? '* * * * *';

            return $this->cronMatchesNow($cron);
        }

        $value = (int) ($config['interval_value'] ?? 1);
        $now = Carbon::now();

        return match ($type) {
            'minutes' => $now->minute % $value === 0 && $now->second < 60,
            'hours' => $now->minute === 0 && $now->hour % $value === 0,
            'days' => $now->hour === 0 && $now->minute === 0,
            default => false,
        };
    }

    private function cronMatchesNow(string $cron): bool
    {
        // Simple cron matching — supports basic 5-field cron expressions
        $parts = preg_split('/\s+/', mb_trim($cron));

        if (count($parts) !== 5) {
            return false;
        }

        $now = Carbon::now();
        [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = $parts;

        return $this->cronFieldMatches($minute, $now->minute)
            && $this->cronFieldMatches($hour, $now->hour)
            && $this->cronFieldMatches($dayOfMonth, $now->day)
            && $this->cronFieldMatches($month, $now->month)
            && $this->cronFieldMatches($dayOfWeek, $now->dayOfWeekIso % 7);
    }

    private function cronFieldMatches(string $field, int $value): bool
    {
        if ($field === '*') {
            return true;
        }

        // Handle */n (every n)
        if (str_starts_with($field, '*/')) {
            $step = (int) mb_substr($field, 2);

            return $step > 0 && $value % $step === 0;
        }

        // Handle comma-separated values
        $values = array_map('intval', explode(',', $field));

        return in_array($value, $values, true);
    }
}
