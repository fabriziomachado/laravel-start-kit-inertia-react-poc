<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Database\Factories;

use Aftandilmmd\WorkflowAutomation\Models\WorkflowTag;
use Illuminate\Database\Eloquent\Factories\Factory;

final class WorkflowTagFactory extends Factory
{
    protected $model = WorkflowTag::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'color' => fake()->hexColor(),
        ];
    }
}
