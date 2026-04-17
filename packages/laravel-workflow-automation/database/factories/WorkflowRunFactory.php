<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Database\Factories;

use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use Illuminate\Database\Eloquent\Factories\Factory;

final class WorkflowRunFactory extends Factory
{
    protected $model = WorkflowRun::class;

    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'status' => RunStatus::Pending,
            'trigger_node_id' => null,
            'initial_payload' => null,
            'context' => null,
            'error_message' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    public function running(): static
    {
        return $this->state([
            'status' => RunStatus::Running,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => RunStatus::Completed,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }

    public function failed(string $error = 'Something went wrong'): static
    {
        return $this->state([
            'status' => RunStatus::Failed,
            'error_message' => $error,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }

    public function waiting(): static
    {
        return $this->state([
            'status' => RunStatus::Waiting,
            'started_at' => now(),
        ]);
    }
}
