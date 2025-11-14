<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Controllers\WebsocketController;
use App\Services\ConnectionService;
use OpenSwoole\WebSocket\Server;

class PingPongTest extends TestCase
{
    private $mockServer;
    private $controller;
    private $connectionService;
    private $capturedResponses = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mock server
        $this->mockServer = $this->createMock(Server::class);
        
        // Create controller
        $this->controller = new WebsocketController($this->mockServer);
        
        // Get connection service via reflection to capture responses
        $reflection = new \ReflectionClass($this->controller);
        $connectionServiceProperty = $reflection->getProperty('connectionService');
        $connectionServiceProperty->setAccessible(true);
        $this->connectionService = $connectionServiceProperty->getValue($this->controller);
        
        // Clear captured responses
        $this->capturedResponses = [];
    }

    public function testPingMessageReturnsCorrectPongResponse(): void
    {
        $fd = 123;
        $timestamp = time();
        
        // Mock the server's isEstablished method to return true
        $this->mockServer->expects($this->once())
            ->method('isEstablished')
            ->with($fd)
            ->willReturn(true);
        
        // Mock the server's push method to capture the response
        $this->mockServer->expects($this->once())
            ->method('push')
            ->with(
                $this->equalTo($fd),
                $this->callback(function ($data) use ($timestamp) {
                    $response = json_decode($data, true);
                    
                    // Verify pong response structure
                    $this->assertIsArray($response);
                    $this->assertSame('pong', $response['type']);
                    $this->assertArrayHasKey('timestamp', $response);
                    $this->assertSame($timestamp, $response['timestamp']);
                    
                    return true;
                })
            )
            ->willReturn(true);
        
        // Create ping message
        $pingMessage = json_encode([
            'action' => 'ping',
            'timestamp' => $timestamp
        ]);
        
        // Create mock frame using the polyfill class
        $frame = new \OpenSwoole\WebSocket\Frame();
        $frame->fd = $fd;
        $frame->data = $pingMessage;
        
        // Handle the ping message
        $this->controller->handleMessage($frame);
    }

    public function testPingWithoutTimestampUsesCurrentTime(): void
    {
        $fd = 456;
        $beforeTime = time();
        
        // Mock the server's isEstablished method to return true
        $this->mockServer->expects($this->once())
            ->method('isEstablished')
            ->with($fd)
            ->willReturn(true);
        
        // Mock the server's push method to capture the response
        $this->mockServer->expects($this->once())
            ->method('push')
            ->with(
                $this->equalTo($fd),
                $this->callback(function ($data) use ($beforeTime) {
                    $response = json_decode($data, true);
                    $afterTime = time();
                    
                    // Verify pong response uses current time when no timestamp provided
                    $this->assertIsArray($response);
                    $this->assertSame('pong', $response['type']);
                    $this->assertArrayHasKey('timestamp', $response);
                    $this->assertGreaterThanOrEqual($beforeTime, $response['timestamp']);
                    $this->assertLessThanOrEqual($afterTime, $response['timestamp']);
                    
                    return true;
                })
            )
            ->willReturn(true);
        
        // Create ping message without timestamp
        $pingMessage = json_encode([
            'action' => 'ping'
        ]);
        
        // Create mock frame using the polyfill class
        $frame = new \OpenSwoole\WebSocket\Frame();
        $frame->fd = $fd;
        $frame->data = $pingMessage;
        
        // Handle the ping message
        $this->controller->handleMessage($frame);
    }

    public function testMultiplePingPongExchanges(): void
    {
        $fd = 789;
        $timestamps = [
            time() - 100,
            time() - 50,
            time()
        ];
        
        // Mock the server's isEstablished method to return true for all calls
        $this->mockServer->expects($this->exactly(3))
            ->method('isEstablished')
            ->with($fd)
            ->willReturn(true);
        
        // Mock the server to expect 3 push calls
        $this->mockServer->expects($this->exactly(3))
            ->method('push')
            ->with($fd, $this->anything())
            ->willReturn(true);
        
        // Send multiple ping messages
        foreach ($timestamps as $timestamp) {
            $pingMessage = json_encode([
                'action' => 'ping',
                'timestamp' => $timestamp
            ]);
            
            $frame = new \OpenSwoole\WebSocket\Frame();
            $frame->fd = $fd;
            $frame->data = $pingMessage;
            
            $this->controller->handleMessage($frame);
        }
    }

    public function testPingResponseIsImmediate(): void
    {
        $fd = 999;
        $timestamp = time();
        
        // Measure response time
        $startTime = microtime(true);
        
        // Mock the server's isEstablished method to return true
        $this->mockServer->expects($this->once())
            ->method('isEstablished')
            ->with($fd)
            ->willReturn(true);
        
        $this->mockServer->expects($this->once())
            ->method('push')
            ->with($fd, $this->anything())
            ->willReturnCallback(function () use ($startTime) {
                $responseTime = microtime(true) - $startTime;
                
                // Response should be very fast (under 10ms in tests)
                $this->assertLessThan(0.01, $responseTime, 'Ping response should be immediate');
                
                return true;
            });
        
        $pingMessage = json_encode([
            'action' => 'ping',
            'timestamp' => $timestamp
        ]);
        
        $frame = new \OpenSwoole\WebSocket\Frame();
        $frame->fd = $fd;
        $frame->data = $pingMessage;
        
        $this->controller->handleMessage($frame);
    }

    public function testPingDoesNotRequireAuthentication(): void
    {
        $fd = 111;
        $timestamp = time();
        
        // Mock the server's isEstablished method to return true
        $this->mockServer->expects($this->once())
            ->method('isEstablished')
            ->with($fd)
            ->willReturn(true);
        
        // Mock server should still respond to ping even without authentication
        $this->mockServer->expects($this->once())
            ->method('push')
            ->with(
                $fd,
                $this->callback(function ($data) {
                    $response = json_decode($data, true);
                    return $response['type'] === 'pong';
                })
            )
            ->willReturn(true);
        
        // Send ping without prior authentication
        $pingMessage = json_encode([
            'action' => 'ping',
            'timestamp' => $timestamp
        ]);
        
        $frame = new \OpenSwoole\WebSocket\Frame();
        $frame->fd = $fd;
        $frame->data = $pingMessage;
        
        $this->controller->handleMessage($frame);
    }
}