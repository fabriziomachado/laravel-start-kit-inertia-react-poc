<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Database\Factories;

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

final class WorkflowFactory extends Factory
{
    protected $model = Workflow::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'is_active' => false,
            'run_async' => true,
            'settings' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    public function sync(): static
    {
        return $this->state(['run_async' => false]);
    }
}
