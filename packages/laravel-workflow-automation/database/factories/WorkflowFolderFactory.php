<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Database\Factories;

use Aftandilmmd\WorkflowAutomation\Models\WorkflowFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

final class WorkflowFolderFactory extends Factory
{
    protected $model = WorkflowFolder::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'parent_id' => null,
        ];
    }

    public function childOf(WorkflowFolder $parent): static
    {
        return $this->state(['parent_id' => $parent->id]);
    }
}
