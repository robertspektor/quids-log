<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Quids Logs Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration options for the Quids Logs package.
    | You can publish this configuration file to your application's config
    | directory to customize the package behavior.
    |
    */

    // Enable or disable Quids Logs
    'enabled' => env('QUIDS_LOGS_ENABLED', true),

    // Your Quids Logs API key
    'api_key' => env('QUIDS_LOGS_API_KEY'),

    // Quids Logs API endpoint
    'endpoint' => env('QUIDS_LOGS_ENDPOINT', 'https://app.quidslogs.com/api/logs/ingest'),

    // Environment (production, staging, local)
    'environment' => env('QUIDS_LOGS_ENVIRONMENT', env('APP_ENV', 'production')),

    // Minimum log level to send
    'log_level' => env('QUIDS_LOGS_LEVEL', 'debug'),

    // Batching configuration
    'batch' => [
        // Maximum number of logs to batch before sending
        'size' => env('QUIDS_LOGS_BATCH_SIZE', 50),
        
        // Maximum time (in seconds) to wait before sending batch
        'timeout' => env('QUIDS_LOGS_BATCH_TIMEOUT', 5),
        
        // Enable/disable batching
        'enabled' => env('QUIDS_LOGS_BATCH_ENABLED', true),
    ],

    // Queue configuration
    'queue' => [
        // Enable queued log transmission
        'enabled' => env('QUIDS_LOGS_QUEUE_ENABLED', true),
        
        // Queue connection to use
        'connection' => env('QUIDS_LOGS_QUEUE_CONNECTION', 'default'),
        
        // Queue name
        'queue' => env('QUIDS_LOGS_QUEUE_NAME', 'quids-logs'),
    ],

    // HTTP client configuration
    'http' => [
        // Request timeout in seconds
        'timeout' => env('QUIDS_LOGS_HTTP_TIMEOUT', 10),
        
        // Connection timeout in seconds
        'connect_timeout' => env('QUIDS_LOGS_HTTP_CONNECT_TIMEOUT', 5),
        
        // Retry configuration
        'retries' => env('QUIDS_LOGS_HTTP_RETRIES', 3),
        
        // Retry delay in milliseconds
        'retry_delay' => env('QUIDS_LOGS_HTTP_RETRY_DELAY', 1000),
    ],

    // Filtering configuration
    'filters' => [
        // Channels to exclude from logging
        'excluded_channels' => [
            // 'single', 'daily'
        ],
        
        // Log levels to exclude
        'excluded_levels' => [
            // 'debug'
        ],
        
        // Exclude logs containing these strings
        'excluded_messages' => [
            // 'password', 'secret'
        ],
    ],

    // Laravel-specific context collection
    'context' => [
        // Collect Laravel framework version
        'laravel_version' => true,
        
        // Collect route information
        'route_info' => true,
        
        // Collect middleware information
        'middleware_info' => true,
        
        // Collect database query information
        'query_info' => true,
        
        // Maximum number of queries to log
        'max_queries' => 10,
        
        // Collect cache information
        'cache_info' => true,
        
        // Collect user information (if authenticated)
        'user_info' => true,
        
        // Collect request information
        'request_info' => true,
    ],

    // Security configuration
    'security' => [
        // Fields to redact from context
        'redacted_fields' => [
            'password',
            'password_confirmation',
            'secret',
            'token',
            'api_key',
            'credit_card',
            'ssn',
        ],
        
        // Enable SSL verification
        'verify_ssl' => env('QUIDS_LOGS_VERIFY_SSL', true),
    ],
];