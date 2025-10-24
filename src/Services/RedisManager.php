<?php

namespace App\Services;

use Redis;
use Exception;

class RedisManager
{
    private static ?Redis $instance = null;
    
    public static function getInstance(): Redis
    {
        if (self::$instance === null) {
            self::$instance = new Redis();
            
            try {
                // Connect to Redis (assuming Redis is running in Docker)
                self::$instance->connect('redis', 6379);
                self::$instance->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
            } catch (Exception $e) {
                echo "Failed to connect to Redis: " . $e->getMessage() . "\n";
                throw $e;
            }
        }
        
        return self::$instance;
    }
}