<?php

namespace App\Services;

use App\Services\RedisManager;
use App\Entities\User;

class UserService
{
    /**
     * Register a new user
     */
    public function registerUser(User $user): void
    {
        $redis = RedisManager::getInstance();
        
        // Store user data
        $redis->hMSet("user:{$user->id}", $user->toRedisHash());
        
        // Add to online users set
        $redis->sAdd('presence:online_users', $user->id);
        
        // Set last seen
        $redis->set("last_seen:{$user->id}", time());
        
        // Store token mapping
        if (!empty($user->token)) {
            $redis->setex("token:{$user->token}", 3600, $user->id); // 1 hour TTL
        }
        
        // Publish user joined event
        $redis->publish('user_events', json_encode([
            'type' => 'user_joined',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'avatar_url' => $user->avatar_url,
            ]
        ]));
    }

    /**
     * Get user by token
     */
    public function getUserByToken(string $token): ?User
    {
        $redis = RedisManager::getInstance();
        
        $userId = $redis->get("token:{$token}");
        if (!$userId) {
            return null;
        }
        
        $userData = $redis->hGetAll("user:{$userId}");
        return User::fromRedisHash($userId, $userData);
    }

    /**
     * Get all online users
     */
    public function getOnlineUsers(): array
    {
        $redis = RedisManager::getInstance();
        
        $userIds = $redis->sMembers('presence:online_users');
        $users = [];
        
        foreach ($userIds as $userId) {
            $userData = $redis->hGetAll("user:{$userId}");
            $user = User::fromRedisHash($userId, $userData);
            if ($user) { $users[] = $user; }
        }
        
        return $users;
    }

    /**
     * Remove user from online presence
     */
    public function removeUserFromPresence(string $userId): void
    {
        $redis = RedisManager::getInstance();
        
        // Remove from online users
        $redis->sRem('presence:online_users', $userId);
        
        // Update last seen
        $redis->set("last_seen:{$userId}", time());
        
        // Publish user left event
        $userData = $redis->hGetAll("user:{$userId}");
        if ($userData) {
            $redis->publish('user_events', json_encode([
                'type' => 'user-left',
                'user' => [
                    'id' => $userId,
                    'username' => $userData['username'] ?? null,
                    'avatar_url' => $userData['avatar_url'] ?? null,
                ]
            ]));
        }
    }
}
