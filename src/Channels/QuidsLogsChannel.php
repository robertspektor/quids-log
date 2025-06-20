<?php

namespace Quids\Logs\Channels;

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Quids\Logs\Client\QuidsLogsClient;
use Quids\Logs\Jobs\SendLogsJob;
use Illuminate\Support\Facades\Queue;

class QuidsLogsChannel
{
    public function __invoke(array $config): Logger
    {
        $client = app(QuidsLogsClient::class);
        $logger = new Logger('quids');
        $handler = new QuidsLogsHandler($client, $config);
        
        $logger->pushHandler($handler);
        
        return $logger;
    }
}

class QuidsLogsHandler extends AbstractProcessingHandler
{
    protected QuidsLogsClient $client;
    protected array $config;
    protected array $logBatch = [];
    protected float $lastFlush;

    public function __construct(QuidsLogsClient $client, array $config = [])
    {
        $this->client = $client;
        $this->config = $config;
        $this->lastFlush = microtime(true);
        
        $level = $config['level'] ?? Logger::DEBUG;
        $bubble = $config['bubble'] ?? true;
        
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        // Skip if disabled
        if (!config('quids-logs.enabled', true)) {
            return;
        }

        // Apply filters
        if ($this->shouldFilter($record)) {
            return;
        }

        $logData = $this->formatRecord($record);

        if (config('quids-logs.batch.enabled', true)) {
            $this->addToBatch($logData);
        } else {
            $this->sendLog($logData);
        }
    }

    protected function shouldFilter(LogRecord $record): bool
    {
        $filters = config('quids-logs.filters', []);

        // Check excluded channels
        if (in_array($record->channel, $filters['excluded_channels'] ?? [])) {
            return true;
        }

        // Check excluded levels
        if (in_array($record->level->getName(), $filters['excluded_levels'] ?? [])) {
            return true;
        }

        // Check excluded messages
        foreach ($filters['excluded_messages'] ?? [] as $excludedMessage) {
            if (str_contains($record->message, $excludedMessage)) {
                return true;
            }
        }

        return false;
    }

    protected function formatRecord(LogRecord $record): array
    {
        $context = $this->enrichContext($record->context);
        $extra = $this->enrichExtra($record->extra);

        // Convert Monolog level to string
        $level = $record->level->getName();
        if (method_exists($record->level, 'toPsrLogLevel')) {
            $level = $record->level->toPsrLogLevel();
        }
        
        // Ensure level is lowercase for API validation
        $level = strtolower($level);

        return [
            'level' => $level,
            'message' => $record->message,
            'environment' => config('quids-logs.environment', 'production'),
            'context' => $context,
            'extra' => $extra,
            'laravel_context' => $this->collectLaravelContext(),
            'channel' => $record->channel,
            'timestamp' => $record->datetime->format('c'),
        ];
    }

    protected function enrichContext(array $context): array
    {
        // Remove sensitive data
        $redactedFields = config('quids-logs.security.redacted_fields', []);
        
        foreach ($redactedFields as $field) {
            if (isset($context[$field])) {
                $context[$field] = '[REDACTED]';
            }
        }

        // Add request ID if available
        if (app()->bound('request') && request()->hasHeader('X-Request-ID')) {
            $context['request_id'] = request()->header('X-Request-ID');
        }

        // Add session ID if available
        if (app()->bound('session') && session()->getId()) {
            $context['session_id'] = session()->getId();
        }

        // Add user ID if authenticated
        if (config('quids-logs.context.user_info', true) && auth()->check()) {
            $context['user_id'] = auth()->id();
        }

        return $context;
    }

    protected function enrichExtra(array $extra): array
    {
        // Add file and line information if available
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        
        foreach ($trace as $frame) {
            if (isset($frame['file']) && !str_contains($frame['file'], 'vendor/')) {
                $extra['file'] = $frame['file'];
                $extra['line'] = $frame['line'] ?? null;
                break;
            }
        }

        return $extra;
    }

    protected function collectLaravelContext(): array
    {
        $context = [];
        $contextConfig = config('quids-logs.context', []);

        // Laravel version
        if ($contextConfig['laravel_version'] ?? true) {
            $context['laravel_version'] = app()->version();
        }

        // Route information
        if (($contextConfig['route_info'] ?? true) && app()->bound('request')) {
            $route = request()->route();
            if ($route) {
                $context['route'] = $route->uri();
                $context['method'] = request()->method();
                $context['controller'] = $route->getActionName();
            }
        }

        // Middleware information
        if (($contextConfig['middleware_info'] ?? true) && app()->bound('request')) {
            $route = request()->route();
            if ($route) {
                $context['middleware'] = $route->gatherMiddleware();
            }
        }

        // Database query information
        if ($contextConfig['query_info'] ?? true) {
            $context['query_count'] = \DB::getQueryLog() ? count(\DB::getQueryLog()) : 0;
        }

        // Memory usage
        $context['memory_usage'] = memory_get_peak_usage(true);

        return $context;
    }

    protected function addToBatch(array $logData): void
    {
        $this->logBatch[] = $logData;

        $batchSize = config('quids-logs.batch.size', 50);
        $batchTimeout = config('quids-logs.batch.timeout', 5);

        // Flush if batch is full or timeout reached
        if (count($this->logBatch) >= $batchSize || 
            (microtime(true) - $this->lastFlush) >= $batchTimeout) {
            $this->flushBatch();
        }
    }

    protected function flushBatch(): void
    {
        if (empty($this->logBatch)) {
            return;
        }

        $logs = $this->logBatch;
        $this->logBatch = [];
        $this->lastFlush = microtime(true);

        if (config('quids-logs.queue.enabled', true)) {
            Queue::connection(config('quids-logs.queue.connection', 'default'))
                ->pushOn(config('quids-logs.queue.queue', 'quids-logs'), new SendLogsJob($logs));
        } else {
            $this->client->sendLogs($logs);
        }
    }

    protected function sendLog(array $logData): void
    {
        if (config('quids-logs.queue.enabled', true)) {
            Queue::connection(config('quids-logs.queue.connection', 'default'))
                ->pushOn(config('quids-logs.queue.queue', 'quids-logs'), new SendLogsJob([$logData]));
        } else {
            $this->client->sendLogs([$logData]);
        }
    }

    public function __destruct()
    {
        // Flush any remaining logs in batch
        $this->flushBatch();
    }
}