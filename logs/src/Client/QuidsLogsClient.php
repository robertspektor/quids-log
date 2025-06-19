<?php

namespace Quids\Logs\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QuidsLogsClient
{
    protected Client $httpClient;
    protected string $apiKey;
    protected string $endpoint;
    protected string $environment;
    protected array $config;

    public function __construct(string $apiKey, string $endpoint, string $environment, array $config = [])
    {
        $this->apiKey = $apiKey;
        $this->endpoint = $endpoint;
        $this->environment = $environment;
        $this->config = $config;

        $this->httpClient = new Client([
            'timeout' => $config['http']['timeout'] ?? 10,
            'connect_timeout' => $config['http']['connect_timeout'] ?? 5,
            'verify' => $config['security']['verify_ssl'] ?? true,
            'headers' => [
                'User-Agent' => 'QuidsLogs-Laravel/' . $this->getPackageVersion(),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-API-Key' => $this->apiKey,
            ],
        ]);
    }

    public function sendLogs(array $logs): bool
    {
        if (empty($logs) || !$this->apiKey) {
            return false;
        }

        // Add request ID for tracking
        $requestId = Str::uuid()->toString();

        $payload = [
            'api_key' => $this->apiKey,
            'logs' => $this->prepareLogs($logs),
            'request_id' => $requestId,
        ];

        return $this->sendWithRetry($payload, $requestId);
    }

    protected function prepareLogs(array $logs): array
    {
        return array_map(function ($log) {
            // Ensure required fields
            $log['environment'] = $log['environment'] ?? $this->environment;
            $log['timestamp'] = $log['timestamp'] ?? now()->toISOString();
            
            // Add request/session IDs if available
            if (app()->bound('request')) {
                $request = request();
                
                if (!isset($log['request_id']) && $request->hasHeader('X-Request-ID')) {
                    $log['request_id'] = $request->header('X-Request-ID');
                }
                
                if (!isset($log['session_id']) && app()->bound('session') && session()->getId()) {
                    $log['session_id'] = session()->getId();
                }
                
                if (!isset($log['user_id']) && auth()->check()) {
                    $log['user_id'] = auth()->id();
                }
            }

            return $log;
        }, $logs);
    }

    protected function sendWithRetry(array $payload, string $requestId): bool
    {
        $maxRetries = $this->config['http']['retries'] ?? 3;
        $retryDelay = $this->config['http']['retry_delay'] ?? 1000;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = $this->httpClient->post($this->endpoint, [
                    'json' => $payload,
                    'headers' => [
                        'X-Request-ID' => $requestId,
                        'X-Attempt' => $attempt + 1,
                    ],
                ]);

                $statusCode = $response->getStatusCode();

                if ($statusCode >= 200 && $statusCode < 300) {
                    // Success
                    $this->logSuccess(count($payload['logs']), $requestId, $attempt);
                    return true;
                }

                // Server error, retry
                if ($statusCode >= 500) {
                    $this->logRetry($requestId, $attempt, "HTTP {$statusCode}");
                    $this->sleep($retryDelay, $attempt);
                    continue;
                }

                // Client error, don't retry
                $this->logError($requestId, "HTTP {$statusCode}", $response->getBody()->getContents());
                return false;

            } catch (ConnectException $e) {
                // Connection issues, retry
                $this->logRetry($requestId, $attempt, "Connection error: " . $e->getMessage());
                
                if ($attempt < $maxRetries) {
                    $this->sleep($retryDelay, $attempt);
                    continue;
                }
                
                $this->logError($requestId, "Connection failed after {$maxRetries} retries", $e->getMessage());
                return false;

            } catch (RequestException $e) {
                // Request issues
                $response = $e->getResponse();
                $statusCode = $response ? $response->getStatusCode() : 0;
                $responseBody = $response ? $response->getBody()->getContents() : $e->getMessage();

                if ($statusCode >= 500 && $attempt < $maxRetries) {
                    // Server error, retry
                    $this->logRetry($requestId, $attempt, "HTTP {$statusCode}");
                    $this->sleep($retryDelay, $attempt);
                    continue;
                }

                $this->logError($requestId, "Request failed: HTTP {$statusCode}", $responseBody);
                return false;

            } catch (\Exception $e) {
                $this->logError($requestId, "Unexpected error", $e->getMessage());
                return false;
            }
        }

        return false;
    }

    protected function sleep(int $baseDelay, int $attempt): void
    {
        // Exponential backoff with jitter
        $delay = $baseDelay * (2 ** $attempt) + random_int(0, 1000);
        usleep($delay * 1000); // Convert to microseconds
    }

    protected function logSuccess(int $logCount, string $requestId, int $attempt): void
    {
        if ($attempt > 0) {
            Log::info("QuidsLogs: Successfully sent {$logCount} logs after {$attempt} retries", [
                'request_id' => $requestId,
                'attempts' => $attempt + 1,
            ]);
        }
    }

    protected function logRetry(string $requestId, int $attempt, string $reason): void
    {
        Log::warning("QuidsLogs: Retry attempt {$attempt} for request {$requestId}: {$reason}");
    }

    protected function logError(string $requestId, string $error, string $details = ''): void
    {
        Log::error("QuidsLogs: Failed to send logs", [
            'request_id' => $requestId,
            'error' => $error,
            'details' => $details,
        ]);
    }

    protected function getPackageVersion(): string
    {
        // Try to read version from composer.json
        $composerPath = __DIR__ . '/../../composer.json';
        
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            return $composer['version'] ?? '1.0.0';
        }

        return '1.0.0';
    }

    public function testConnection(): array
    {
        try {
            $testLog = [
                'level' => 'info',
                'message' => 'QuidsLogs connection test',
                'environment' => $this->environment,
                'timestamp' => now()->toISOString(),
                'context' => ['test' => true],
            ];

            $success = $this->sendLogs([$testLog]);

            return [
                'success' => $success,
                'message' => $success ? 'Connection test successful' : 'Connection test failed',
                'endpoint' => $this->endpoint,
                'environment' => $this->environment,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
                'endpoint' => $this->endpoint,
                'environment' => $this->environment,
            ];
        }
    }
}