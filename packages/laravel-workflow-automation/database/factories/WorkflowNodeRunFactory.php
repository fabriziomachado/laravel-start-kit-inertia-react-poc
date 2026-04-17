<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Database\Factories;

use Aftandilmmd\WorkflowAutomation\Enums\NodeRunStatus;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNodeRun;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use Illuminate\Database\Eloquent\Factories\Factory;

final class WorkflowNodeRunFactory extends Factory
{
    protected $model = WorkflowNodeRun::class;

    public function definition(): array
    {
        return [
            'workflow_run_id' => WorkflowRun::factory(),
            'node_id' => WorkflowNode::factory(),
            'status' => NodeRunStatus::Pending,
            'input' => null,
            'output' => null,
            'error_message' => null,
            'duration_ms' => null,
            'attempts' => 0,
            'executed_at' => null,
        ];
    }

    public function completed(array $output = []): static
    {
        return $this->state([
            'status' => NodeRunStatus::Completed,
            'output' => $output,
            'duration_ms' => fake()->numberBetween(10, 5000),
            'executed_at' => now(),
        ]);
    }

    public function failed(string $error = 'Node execution failed'): static
    {
        return $this->state([
            'status' => NodeRunStatus::Failed,
            'error_message' => $error,
            'executed_at' => now(),
        ]);
    }
}
