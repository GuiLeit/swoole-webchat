<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Mockery;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearRedis();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Clear Redis for clean test state
     */
    protected function clearRedis(): void
    {
        try {
            $redis = new \Redis();
            $redis->connect('redis', 6379);
            $redis->flushDB();
        } catch (\Exception $e) {
            // Redis not available, skip
        }
    }

    /**
     * Get a mock Redis instance
     */
    protected function getMockRedis(): \Redis
    {
        return Mockery::mock(\Redis::class);
    }
}