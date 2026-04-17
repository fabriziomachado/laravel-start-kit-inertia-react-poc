<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Database\Factories;

use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;
use Illuminate\Database\Eloquent\Factories\Factory;

final class WorkflowNodeFactory extends Factory
{
    protected $model = WorkflowNode::class;

    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'type' => NodeType::Action,
            'node_key' => 'manual',
            'name' => null,
            'config' => [],
            'position_x' => fake()->numberBetween(0, 800),
            'position_y' => fake()->numberBetween(0, 600),
        ];
    }

    public function trigger(string $key = 'manual'): static
    {
        return $this->state([
            'type' => NodeType::Trigger,
            'node_key' => $key,
        ]);
    }

    public function action(string $key = 'send_mail'): static
    {
        return $this->state([
            'type' => NodeType::Action,
            'node_key' => $key,
        ]);
    }

    public function condition(string $key = 'if_condition'): static
    {
        return $this->state([
            'type' => NodeType::Condition,
            'node_key' => $key,
        ]);
    }

    public function withConfig(array $config): static
    {
        return $this->state(['config' => $config]);
    }

    public function withPinnedInput(array $input): static
    {
        return $this->state(fn (array $attributes) => [
            'pinned_data' => array_merge($attributes['pinned_data'] ?? [], ['input' => $input]),
        ]);
    }

    public function withPinnedOutput(array $output): static
    {
        return $this->state(fn (array $attributes) => [
            'pinned_data' => array_merge($attributes['pinned_data'] ?? [], ['output' => $output]),
        ]);
    }
}
