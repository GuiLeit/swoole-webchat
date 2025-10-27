<?php

namespace App\Repositories;

use App\Services\RedisManager;
use App\Entities\User;

class UserRepository
{
    private \Redis $redis;

    public function __construct()
    {
        $this->redis = RedisManager::getInstance();
    }

    /**
     * Store user data in Redis
     */
    public function storeUser(User $user): void
    {
        $this->redis->hMSet("user:{$user->id}", $user->toRedisHash());
        
        // Store token mapping with 1 hour TTL
        if (!empty($user->token)) {
            $this->redis->setex("token:{$user->token}", 3600, $user->id);
        }
    }

    /**
     * Get user by ID
     */
    public function getUserById(string $userId): ?User
    {
        $userData = $this->redis->hGetAll("user:{$userId}");
        return User::fromRedisHash($userId, $userData);
    }

    /**
     * Get user by authentication token
     */
    public function getUserByToken(string $token): ?User
    {
        $userId = $this->redis->get("token:{$token}");
        if (!$userId) {
            return null;
        }
        
        return $this->getUserById($userId);
    }

    /**
     * Add user to online users set
     */
    public function addToOnlineUsers(string $userId): void
    {
        $this->redis->sAdd('presence:online_users', $userId);
    }

    /**
     * Remove user from online users set
     */
    public function removeFromOnlineUsers(string $userId): void
    {
        $this->redis->sRem('presence:online_users', $userId);
    }

    /**
     * Get all online users
     */
    public function getAllOnlineUsers(): array
    {
        $userIds = $this->redis->sMembers('presence:online_users');
        $users = [];
        
        foreach ($userIds as $userId) {
            $user = $this->getUserById($userId);
            if ($user) {
                $users[] = $user;
            }
        }
        
        return $users;
    }

    /**
     * Set user's last seen timestamp
     */
    public function setLastSeen(string $userId, int $timestamp): void
    {
        $this->redis->set("last_seen:{$userId}", $timestamp);
    }

    /**
     * Get user's last seen timestamp
     */
    public function getLastSeen(string $userId): ?int
    {
        $timestamp = $this->redis->get("last_seen:{$userId}");
        return $timestamp !== false ? (int)$timestamp : null;
    }

    /**
     * Publish user event to Redis pub/sub
     */
    public function publishUserEvent(string $eventType, array $userData): void
    {
        $this->redis->publish('user_events', json_encode([
            'type' => $eventType,
            'user' => $userData
        ]));
    }
}
