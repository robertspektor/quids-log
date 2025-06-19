<?php

namespace Quids\Logs\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Quids\Logs\QuidsLogsServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up test configuration
        config([
            'quids-logs.enabled' => true,
            'quids-logs.api_key' => 'ql_test_key_1234567890',
            'quids-logs.endpoint' => 'https://test.quidslogs.com/api/logs/ingest',
            'quids-logs.environment' => 'testing',
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [
            QuidsLogsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Set up test environment
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}