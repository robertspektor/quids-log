# Quids Logs - Laravel Package

[![Latest Version](https://img.shields.io/packagist/v/quids/logs.svg?style=flat-square)](https://packagist.org/packages/quids/logs)
[![Total Downloads](https://img.shields.io/packagist/dt/quids/logs.svg?style=flat-square)](https://packagist.org/packages/quids/logs)

Laravel package for sending logs to Quids Logs SaaS platform. Provides seamless integration with Laravel's logging system and includes Laravel-specific context collection.

## Features

- 🚀 **Zero-config setup** - Works out of the box with minimal configuration
- 📦 **Batched transmission** - Efficient log batching with configurable size and timeout
- 🔄 **Queue support** - Asynchronous log transmission using Laravel queues
- 🛡️ **Security focused** - Automatic PII redaction and secure transmission
- 📊 **Laravel-specific context** - Collects route, middleware, query, and performance data
- 🔁 **Retry mechanisms** - Robust error handling with exponential backoff
- 🧪 **Fully tested** - Comprehensive test suite

## Installation

Install the package via Composer:

```bash
composer require quids/logs
```

The package will automatically register its service provider.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Quids\Logs\QuidsLogsServiceProvider" --tag="quids-logs-config"
```

Add your Quids Logs API key to your `.env` file:

```env
QUIDS_LOGS_API_KEY=your_api_key_here
QUIDS_LOGS_ENVIRONMENT=production
```

## Quick Start

Once configured, the package automatically integrates with Laravel's logging system:

```php
use Illuminate\Support\Facades\Log;

// Logs will automatically be sent to Quids Logs
Log::info('User logged in', ['user_id' => 123]);
Log::error('Payment failed', ['order_id' => 456, 'error' => 'Card declined']);
```

## Manual Usage

You can also send logs directly using the client:

```php
use Quids\Logs\Client\QuidsLogsClient;

$client = app(QuidsLogsClient::class);

$client->sendLogs([
    [
        'level' => 'info',
        'message' => 'Custom log message',
        'context' => ['key' => 'value'],
        'timestamp' => now()->toISOString(),
    ]
]);
```

## Configuration Options

### Environment Variables

```env
# Required
QUIDS_LOGS_API_KEY=your_api_key_here

# Optional
QUIDS_LOGS_ENABLED=true
QUIDS_LOGS_ENVIRONMENT=production
QUIDS_LOGS_ENDPOINT=https://app.quidslogs.com/api/logs/ingest
QUIDS_LOGS_LEVEL=debug

# Batching
QUIDS_LOGS_BATCH_ENABLED=true
QUIDS_LOGS_BATCH_SIZE=50
QUIDS_LOGS_BATCH_TIMEOUT=5

# Queue
QUIDS_LOGS_QUEUE_ENABLED=true
QUIDS_LOGS_QUEUE_CONNECTION=default
QUIDS_LOGS_QUEUE_NAME=quids-logs

# HTTP
QUIDS_LOGS_HTTP_TIMEOUT=10
QUIDS_LOGS_HTTP_RETRIES=3
```

### Advanced Configuration

The published config file provides extensive customization options:

```php
return [
    'enabled' => env('QUIDS_LOGS_ENABLED', true),
    'api_key' => env('QUIDS_LOGS_API_KEY'),
    
    // Filtering
    'filters' => [
        'excluded_channels' => ['single'],
        'excluded_levels' => ['debug'],
        'excluded_messages' => ['password', 'secret'],
    ],
    
    // Context collection
    'context' => [
        'laravel_version' => true,
        'route_info' => true,
        'query_info' => true,
        'user_info' => true,
    ],
    
    // Security
    'security' => [
        'redacted_fields' => ['password', 'secret', 'token'],
    ],
];
```

## Laravel-Specific Features

The package automatically collects Laravel-specific context:

- **Route information** - Route name, controller, middleware
- **Database queries** - Query count and execution time
- **Cache operations** - Hit/miss ratios
- **User context** - Authenticated user ID
- **Request context** - Request ID, session ID
- **Performance metrics** - Memory usage, response time

## Queue Integration

For high-throughput applications, enable queue-based log transmission:

```env
QUIDS_LOGS_QUEUE_ENABLED=true
QUIDS_LOGS_QUEUE_CONNECTION=redis
QUIDS_LOGS_QUEUE_NAME=logs
```

Make sure to run queue workers:

```bash
php artisan queue:work --queue=logs
```

## Testing

Test the connection to Quids Logs:

```php
use Quids\Logs\Client\QuidsLogsClient;

$client = app(QuidsLogsClient::class);
$result = $client->testConnection();

if ($result['success']) {
    echo "Connection successful!";
} else {
    echo "Connection failed: " . $result['message'];
}
```

## Security

- Sensitive fields are automatically redacted from log context
- All transmission uses HTTPS with SSL verification
- API keys are securely transmitted via headers
- No sensitive data is stored locally

## Performance

- Logs are batched for efficient transmission
- Background queue processing prevents blocking
- Exponential backoff retry mechanism
- Configurable timeouts and limits

## Error Handling

The package handles various error scenarios gracefully:

- Network connectivity issues
- API rate limiting
- Server errors (with retry)
- Invalid configurations

Failed logs are automatically retried with exponential backoff.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review our [security policy](SECURITY.md) on how to report security vulnerabilities.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Support

- Documentation: [https://docs.quidslogs.com](https://docs.quidslogs.com)
- Issues: [GitHub Issues](https://github.com/robertspektor/quids-log/issues)
- Email: support@quidslogs.com