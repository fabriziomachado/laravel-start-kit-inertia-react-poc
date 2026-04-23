<?php

declare(strict_types=1);

namespace App\Providers;

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use App\Observers\WorkflowDefinitionObserver;
use App\Policies\WorkflowPolicy;
use App\Policies\WorkflowRunPolicy;
use App\Services\Workflow\AiFieldExtractor;
use App\Services\Workflow\NoopAiFieldExtractor;
use App\Services\Workflow\OpenAiFieldExtractor;
use App\Workflow\Presentations\RegisterWorkflowPresentations;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiFieldExtractor::class, function (): AiFieldExtractor {
            $openAi = new OpenAiFieldExtractor;

            return $openAi->isAvailable() ? $openAi : new NoopAiFieldExtractor;
        });
    }

    public function boot(): void
    {
        Gate::policy(Workflow::class, WorkflowPolicy::class);
        Gate::policy(WorkflowRun::class, WorkflowRunPolicy::class);

        Gate::define('viewWorkflowAutomation', function ($user = null): bool {
            return (bool) ($user?->is_admin ?? false);
        });

        Workflow::observe(WorkflowDefinitionObserver::class);

        RegisterWorkflowPresentations::register();
    }
}
