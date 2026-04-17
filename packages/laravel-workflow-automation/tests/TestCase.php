<?php

namespace Aftandilmmd\WorkflowAutomation\Tests;

use Aftandilmmd\WorkflowAutomation\WorkflowAutomationServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            WorkflowAutomationServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('workflow-automation.async', false);
        $app['config']->set('workflow-automation.routes', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('viewWorkflowAutomation', fn ($user = null) => true);
    }
}
