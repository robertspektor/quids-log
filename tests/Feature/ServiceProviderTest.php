<?php

namespace Quids\Logs\Tests\Feature;

use Quids\Logs\Client\QuidsLogsClient;
use Quids\Logs\Tests\TestCase;
use Illuminate\Support\Facades\Log;

class ServiceProviderTest extends TestCase
{
    public function test_service_provider_registers_client()
    {
        $client = app(QuidsLogsClient::class);

        $this->assertInstanceOf(QuidsLogsClient::class, $client);
    }

    public function test_config_is_published()
    {
        $this->assertEquals('ql_test_key_1234567890', config('quids-logs.api_key'));
        $this->assertEquals('testing', config('quids-logs.environment'));
        $this->assertTrue(config('quids-logs.enabled'));
    }

    public function test_log_channel_is_registered()
    {
        config([
            'logging.channels.quids' => [
                'driver' => 'quids',
                'level' => 'debug',
            ]
        ]);

        $logger = Log::channel('quids');

        $this->assertNotNull($logger);
    }

    public function test_auto_configuration_adds_quids_to_stack()
    {
        // Reset config to test auto-configuration
        config([
            'logging.channels.stack.channels' => ['single'],
            'quids-logs.enabled' => true,
            'quids-logs.api_key' => 'test_key',
        ]);

        // Re-register service provider to trigger auto-configuration
        $provider = new \Quids\Logs\QuidsLogsServiceProvider(app());
        $provider->boot();

        $stackChannels = config('logging.channels.stack.channels');
        
        $this->assertContains('quids', $stackChannels);
    }

    public function test_auto_configuration_does_not_duplicate_quids_channel()
    {
        // Set up config with quids already in stack
        config([
            'logging.channels.stack.channels' => ['single', 'quids'],
            'quids-logs.enabled' => true,
            'quids-logs.api_key' => 'test_key',
        ]);

        // Re-register service provider
        $provider = new \Quids\Logs\QuidsLogsServiceProvider(app());
        $provider->boot();

        $stackChannels = config('logging.channels.stack.channels');
        $quidChannelCount = array_count_values($stackChannels)['quids'] ?? 0;
        
        $this->assertEquals(1, $quidChannelCount);
    }
}