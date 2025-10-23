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
    
    /**
     * Generate a unique user ID
     */
    public static function generateUserId(): string
    {
        return 'user_' . bin2hex(random_bytes(16));
    }
    
    /**
     * Generate a secure token
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Register a new user
     */
    public static function registerUser(string $userId, array $userData): void
    {
        $redis = self::getInstance();
        
        // Store user data
        $redis->hMSet("user:{$userId}", $userData);
        
        // Add to online users set
        $redis->sAdd('presence:online_users', $userId);
        
        // Set last seen
        $redis->set("last_seen:{$userId}", time());
        
        // Store token mapping
        if (isset($userData['token'])) {
            $redis->setex("token:{$userData['token']}", 3600, $userId); // 1 hour TTL
        }
        
        // Publish user joined event
        $redis->publish('user_events', json_encode([
            'type' => 'user_joined',
            'user' => [
                'id' => $userId,
                'username' => $userData['username'],
                'avatar_url' => $userData['avatar_url']
            ]
        ]));
    }
    
    /**
     * Get user by token
     */
    public static function getUserByToken(string $token): ?array
    {
        $redis = self::getInstance();
        
        $userId = $redis->get("token:{$token}");
        if (!$userId) {
            return null;
        }
        
        $userData = $redis->hGetAll("user:{$userId}");
        if ($userData) {
            $userData['id'] = $userId;
        }
        
        return $userData ?: null;
    }
    
    /**
     * Get all online users
     */
    public static function getOnlineUsers(): array
    {
        $redis = self::getInstance();
        
        $userIds = $redis->sMembers('presence:online_users');
        $users = [];
        
        foreach ($userIds as $userId) {
            $userData = $redis->hGetAll("user:{$userId}");
            if ($userData) {
                $userData['id'] = $userId;
                $users[] = $userData;
            }
        }
        
        return $users;
    }
    
    /**
     * Remove user from online presence
     */
    public static function removeUserFromPresence(string $userId): void
    {
        $redis = self::getInstance();
        
        // Remove from online users
        $redis->sRem('presence:online_users', $userId);
        
        // Update last seen
        $redis->set("last_seen:{$userId}", time());
        
        // Publish user left event
        $userData = $redis->hGetAll("user:{$userId}");
        if ($userData) {
            $redis->publish('user_events', json_encode([
                'type' => 'user_left',
                'user' => [
                    'id' => $userId,
                    'username' => $userData['username'],
                    'avatar_url' => $userData['avatar_url']
                ]
            ]));
        }
    }
}