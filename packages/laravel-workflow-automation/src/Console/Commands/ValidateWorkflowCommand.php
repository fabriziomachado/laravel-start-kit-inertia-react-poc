<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Console\Commands;

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Services\WorkflowService;
use Illuminate\Console\Command;

final class ValidateWorkflowCommand extends Command
{
    protected $signature = 'workflow:validate {workflow : The workflow ID to validate}';

    protected $description = 'Validate a workflow graph and print any errors.';

    public function handle(WorkflowService $service): int
    {
        $workflow = Workflow::find($this->argument('workflow'));

        if (! $workflow) {
            $this->components->error('Workflow not found.');

            return self::FAILURE;
        }

        $errors = $service->validate($workflow);

        if (empty($errors)) {
            $this->components->info("Workflow #{$workflow->id} \"{$workflow->name}\" is valid.");

            return self::SUCCESS;
        }

        $this->components->error("Workflow #{$workflow->id} \"{$workflow->name}\" has ".count($errors).' error(s):');

        foreach ($errors as $error) {
            $this->components->bulletList([$error]);
        }

        return self::FAILURE;
    }
}
