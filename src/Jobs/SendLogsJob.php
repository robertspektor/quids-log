<?php

namespace Quids\Logs\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Quids\Logs\Client\QuidsLogsClient;

class SendLogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $logs;
    
    public int $tries = 3;
    public int $maxExceptions = 3;
    public int $timeout = 30;
    public int $backoff = 5;

    public function __construct(array $logs)
    {
        $this->logs = $logs;
        
        // Set queue configuration from config
        $this->onConnection(config('quids-logs.queue.connection', 'default'));
        $this->onQueue(config('quids-logs.queue.queue', 'quids-logs'));
    }

    public function handle(QuidsLogsClient $client): void
    {
        try {
            $success = $client->sendLogs($this->logs);
            
            if (!$success) {
                throw new \Exception('Failed to send logs to Quids Logs API');
            }
        } catch (\Exception $e) {
            Log::error('QuidsLogs: Failed to send logs in background job', [
                'logs_count' => count($this->logs),
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('QuidsLogs: SendLogsJob failed permanently', [
            'logs_count' => count($this->logs),
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);
    }

    public function retryUntil(): \DateTime
    {
        // Retry for up to 1 hour
        return now()->addHour();
    }
}