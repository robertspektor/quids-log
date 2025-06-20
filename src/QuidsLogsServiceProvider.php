<?php

namespace Quids\Logs;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Quids\Logs\Channels\QuidsLogsChannel;
use Quids\Logs\Client\QuidsLogsClient;

class QuidsLogsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/quids-logs.php',
            'quids-logs'
        );

        $this->app->singleton(QuidsLogsClient::class, function ($app) {
            return new QuidsLogsClient(
                config('quids-logs.api_key'),
                config('quids-logs.endpoint'),
                config('quids-logs.environment'),
                config('quids-logs')
            );
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/quids-logs.php' => config_path('quids-logs.php'),
        ], 'quids-logs-config');

        // Register custom log channel
        Log::extend('quids', function ($app, $config) {
            $channel = new QuidsLogsChannel();
            return $channel($config);
        });

        // Auto-configure if API key is set
        if (config('quids-logs.enabled') && config('quids-logs.api_key')) {
            $this->autoConfigureLogging();
        }
    }

    protected function autoConfigureLogging()
    {
        // Add quids channel to default logging stack if not already present
        $channels = config('logging.channels.stack.channels', []);
        
        if (!in_array('quids', $channels)) {
            config(['logging.channels.stack.channels' => array_merge($channels, ['quids'])]);
        }

        // Configure quids channel
        config([
            'logging.channels.quids' => [
                'driver' => 'quids',
                'level' => config('quids-logs.log_level', 'debug'),
                'environment' => config('quids-logs.environment'),
            ]
        ]);
    }
}