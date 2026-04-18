<?php

declare(strict_types=1);

namespace App\Providers;

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use App\Observers\WorkflowDefinitionObserver;
use App\Policies\WorkflowPolicy;
use App\Policies\WorkflowRunPolicy;
use App\Workflow\Presentations\RegisterWorkflowPresentations;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Workflow::class, WorkflowPolicy::class);
        Gate::policy(WorkflowRun::class, WorkflowRunPolicy::class);

        Workflow::observe(WorkflowDefinitionObserver::class);

        RegisterWorkflowPresentations::register();
    }
}
