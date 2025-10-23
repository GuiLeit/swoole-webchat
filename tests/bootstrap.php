<?php

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