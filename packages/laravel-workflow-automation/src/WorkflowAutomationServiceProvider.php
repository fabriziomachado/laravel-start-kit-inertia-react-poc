<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation;

use Aftandilmmd\WorkflowAutomation\Contracts\ExpressionEvaluatorInterface;
use Aftandilmmd\WorkflowAutomation\Credentials\CredentialResolutionMiddleware;
use Aftandilmmd\WorkflowAutomation\Credentials\CredentialTypeRegistry;
use Aftandilmmd\WorkflowAutomation\Credentials\Types\ApiKeyCredential;
use Aftandilmmd\WorkflowAutomation\Credentials\Types\BasicAuthCredential;
use Aftandilmmd\WorkflowAutomation\Credentials\Types\BearerTokenCredential;
use Aftandilmmd\WorkflowAutomation\Credentials\Types\HeaderAuthCredential;
use Aftandilmmd\WorkflowAutomation\Engine\ExpressionEvaluator;
use Aftandilmmd\WorkflowAutomation\Engine\GraphExecutor;
use Aftandilmmd\WorkflowAutomation\Engine\GraphValidator;
use Aftandilmmd\WorkflowAutomation\Engine\NodeRunner;
use Aftandilmmd\WorkflowAutomation\Plugin\PluginManager;
use Aftandilmmd\WorkflowAutomation\Plugin\PluginRegistry;
use Aftandilmmd\WorkflowAutomation\Registry\NodeRegistry;
use Aftandilmmd\WorkflowAutomation\Services\ConcurrencyGuard;
use Aftandilmmd\WorkflowAutomation\Services\WorkflowService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class WorkflowAutomationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/workflow-automation.php', 'workflow-automation');

        $this->app->singleton(NodeRegistry::class);
        $this->app->singleton(NodeRunner::class);
        $this->app->singleton(PluginRegistry::class);
        $this->app->singleton(PluginManager::class);
        $this->app->singleton(CredentialTypeRegistry::class);

        $this->app->singleton(
            ExpressionEvaluatorInterface::class,
            ExpressionEvaluator::class,
        );

        $this->app->singleton(GraphValidator::class, fn ($app) => new GraphValidator(
            $app->make(NodeRegistry::class),
        ));

        $this->app->singleton(ConcurrencyGuard::class);

        $this->app->singleton(GraphExecutor::class, fn ($app) => new GraphExecutor(
            registry: $app->make(NodeRegistry::class),
            nodeRunner: $app->make(NodeRunner::class),
            expressionEvaluator: $app->make(ExpressionEvaluatorInterface::class),
            graphValidator: $app->make(GraphValidator::class),
            concurrencyGuard: $app->make(ConcurrencyGuard::class),
        ));

        $this->app->singleton(WorkflowService::class, fn ($app) => new WorkflowService(
            executor: $app->make(GraphExecutor::class),
            validator: $app->make(GraphValidator::class),
            concurrencyGuard: $app->make(ConcurrencyGuard::class),
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (config('workflow-automation.routes', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }

        if (config('workflow-automation.editor_routes', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/editor.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\ScheduleRunCommand::class,
                Console\Commands\CleanRunsCommand::class,
                Console\Commands\ValidateWorkflowCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/workflow-automation.php' => config_path('workflow-automation.php'),
            ], 'workflow-automation-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'workflow-automation-migrations');

            $this->publishes([
                __DIR__.'/../ui/dist' => public_path('workflow-editor'),
            ], 'workflow-automation-editor');
        }

        $this->authorization();

        $this->registerBuiltInNodes();
        $this->registerBuiltInCredentialTypes();
        $this->registerConfigPlugins();
        $this->bootPlugins();
        $this->registerListeners();
        $this->registerMcpServer();
    }

    /**
     * Register the default authorization gate.
     *
     * This gate determines who can access Workflow Automation in non-local
     * environments. In local, access is always allowed.
     *
     * Override in your AppServiceProvider:
     *
     *     Gate::define('viewWorkflowAutomation', function ($user) {
     *         return in_array($user->email, ['admin@example.com']);
     *     });
     */
    protected function authorization(): void
    {
        $this->gate();
    }

    protected function gate(): void
    {
        if (Gate::has('viewWorkflowAutomation')) {
            return;
        }

        Gate::define('viewWorkflowAutomation', function ($user = null) {
            return false;
        });
    }

    private function registerListeners(): void
    {
        $this->app->booted(function () {
            Listeners\ModelEventListener::register();
            Listeners\EventListener::register();
            Listeners\WorkflowChainListener::register();
        });
    }

    private function registerMcpServer(): void
    {
        if (! config('workflow-automation.mcp.enabled', false)) {
            return;
        }

        if (! class_exists(\Laravel\Mcp\Facades\Mcp::class)) {
            return;
        }

        \Laravel\Mcp\Facades\Mcp::web(
            config('workflow-automation.mcp.path', '/mcp/workflow'),
            Mcp\WorkflowMcpServer::class,
        );
    }

    private function registerConfigPlugins(): void
    {
        $manager = $this->app->make(PluginManager::class);

        foreach (config('workflow-automation.plugins', []) as $pluginClass) {
            if (is_string($pluginClass) && class_exists($pluginClass)) {
                $manager->plugin($pluginClass::make());
            }
        }
    }

    private function bootPlugins(): void
    {
        $this->app->make(PluginManager::class)->bootPlugins();
    }

    private function registerBuiltInNodes(): void
    {
        $registry = $this->app->make(NodeRegistry::class);
        $registry->discoverNodes(__DIR__.'/Nodes');

        foreach (config('workflow-automation.node_discovery.app_paths', []) as $path) {
            if (is_dir($path)) {
                $registry->discoverNodes($path);
            }
        }
    }

    private function registerBuiltInCredentialTypes(): void
    {
        $registry = $this->app->make(CredentialTypeRegistry::class);

        $registry->register(BearerTokenCredential::class);
        $registry->register(BasicAuthCredential::class);
        $registry->register(HeaderAuthCredential::class);
        $registry->register(ApiKeyCredential::class);

        $this->app->make(NodeRunner::class)->pushMiddleware(CredentialResolutionMiddleware::class);
    }
}
