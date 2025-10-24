<?php

namespace Tests\Unit;

// use PHPUnit\Framework\TestCase;
use Tests\TestCase;
use App\Services\RedisManager;

class RedisManagerTest extends TestCase
{
    public function testRedisConnectionAndSetGet(): void
    {
        $redis = RedisManager::getInstance();

        $key = 'test:key:' . bin2hex(random_bytes(4));
        $value = 'ok';
        $redis->set($key, $value);

        $this->assertEquals($value, $redis->get($key));
    }
}