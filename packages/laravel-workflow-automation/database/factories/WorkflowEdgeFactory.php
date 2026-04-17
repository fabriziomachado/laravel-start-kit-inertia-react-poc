<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Database\Factories;

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowEdge;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;
use Illuminate\Database\Eloquent\Factories\Factory;

final class WorkflowEdgeFactory extends Factory
{
    protected $model = WorkflowEdge::class;

    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'source_node_id' => WorkflowNode::factory(),
            'source_port' => 'main',
            'target_node_id' => WorkflowNode::factory(),
            'target_port' => 'main',
        ];
    }

    public function fromPort(string $port): static
    {
        return $this->state(['source_port' => $port]);
    }

    public function toPort(string $port): static
    {
        return $this->state(['target_port' => $port]);
    }
}
