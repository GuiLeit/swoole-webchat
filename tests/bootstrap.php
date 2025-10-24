<?php

// Polyfills for OpenSwoole classes to allow unit tests without the extension
// Only defined if extension is not installed in the test runtime.
namespace OpenSwoole\WebSocket {
    if (!class_exists(Server::class)) {
        class Server
        {
            public function isEstablished(int $fd): bool
            {
                return true;
            }
            public function push(int $fd, string $data): bool
            {
                return true;
            }
        }
    }
}

namespace OpenSwoole\Http {
    if (!class_exists(Request::class)) {
        class Request {}
    }
}

namespace OpenSwoole\WebSocket {
    if (!class_exists(Frame::class)) {
        class Frame
        {
            public int $fd = 0;
            public string $data = '';
        }
    }
}

// Back to global namespace
namespace {
    require_once __DIR__ . '/../vendor/autoload.php';

    // Load environment variables for testing
    $_ENV['APP_ENV'] = 'testing';
    $_ENV['REDIS_HOST'] = 'redis';
    $_ENV['REDIS_PORT'] = '6379';

    // Clean up Redis before tests
    if (getenv('APP_ENV') === 'testing') {
        try {
            $redis = new Redis();
            $redis->connect('redis', 6379);
            $redis->flushDB(); // Clear test database
        } catch (Exception $e) {
            echo "Warning: Could not connect to Redis for test setup\n";
        }
    }
}
