<?php

namespace Quids\Logs\Tests\Unit;

use Quids\Logs\Client\QuidsLogsClient;
use Quids\Logs\Tests\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;

class QuidsLogsClientTest extends TestCase
{
    protected function createMockClient(array $responses): QuidsLogsClient
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        
        $client = new QuidsLogsClient(
            'ql_test_key',
            'https://test.example.com/api/logs/ingest',
            'testing',
            [
                'http' => [
                    'timeout' => 5,
                    'retries' => 2,
                    'retry_delay' => 100,
                ],
            ]
        );

        // Use reflection to inject mock HTTP client
        $reflection = new \ReflectionClass($client);
        $httpClientProperty = $reflection->getProperty('httpClient');
        $httpClientProperty->setAccessible(true);
        $httpClientProperty->setValue($client, new Client(['handler' => $handlerStack]));

        return $client;
    }

    public function test_send_logs_success()
    {
        $client = $this->createMockClient([
            new Response(200, [], json_encode(['success' => true])),
        ]);

        $logs = [
            [
                'level' => 'info',
                'message' => 'Test message',
                'environment' => 'testing',
                'timestamp' => now()->toISOString(),
            ],
        ];

        $result = $client->sendLogs($logs);

        $this->assertTrue($result);
    }

    public function test_send_logs_with_retry_on_server_error()
    {
        $client = $this->createMockClient([
            new Response(500, [], 'Server Error'),
            new Response(200, [], json_encode(['success' => true])),
        ]);

        $logs = [
            [
                'level' => 'error',
                'message' => 'Test error',
                'environment' => 'testing',
                'timestamp' => now()->toISOString(),
            ],
        ];

        $result = $client->sendLogs($logs);

        $this->assertTrue($result);
    }

    public function test_send_logs_fails_on_client_error()
    {
        $client = $this->createMockClient([
            new Response(400, [], 'Bad Request'),
        ]);

        $logs = [
            [
                'level' => 'info',
                'message' => 'Test message',
                'environment' => 'testing',
                'timestamp' => now()->toISOString(),
            ],
        ];

        $result = $client->sendLogs($logs);

        $this->assertFalse($result);
    }

    public function test_send_logs_fails_after_max_retries()
    {
        $client = $this->createMockClient([
            new Response(500, [], 'Server Error'),
            new Response(500, [], 'Server Error'),
            new Response(500, [], 'Server Error'),
        ]);

        $logs = [
            [
                'level' => 'info',
                'message' => 'Test message',
                'environment' => 'testing',
                'timestamp' => now()->toISOString(),
            ],
        ];

        $result = $client->sendLogs($logs);

        $this->assertFalse($result);
    }

    public function test_send_empty_logs_returns_false()
    {
        $client = $this->createMockClient([]);

        $result = $client->sendLogs([]);

        $this->assertFalse($result);
    }

    public function test_test_connection_success()
    {
        $client = $this->createMockClient([
            new Response(200, [], json_encode(['success' => true])),
        ]);

        $result = $client->testConnection();

        $this->assertTrue($result['success']);
        $this->assertEquals('Connection test successful', $result['message']);
    }

    public function test_test_connection_failure()
    {
        $client = $this->createMockClient([
            new Response(500, [], 'Server Error'),
            new Response(500, [], 'Server Error'),
            new Response(500, [], 'Server Error'),
        ]);

        $result = $client->testConnection();

        $this->assertFalse($result['success']);
        $this->assertStringContains('Connection test failed', $result['message']);
    }
}