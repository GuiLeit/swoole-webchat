<?php

namespace App\Services;

use App\Services\RedisManager;

class AuthService
{
    /**
     * Authenticate a user with username and optional token
     */
    public function authenticate(string $username, string $avatarUrl = '', ?string $token = null): array
    {
        if (empty($username)) {
            throw new \InvalidArgumentException('Username is required');
        }
        
        $userId = null;
        $userData = null;
        
        // Check if user has existing token
        if ($token) {
            $userData = RedisManager::getUserByToken($token);
            if ($userData) {
                $userId = $userData['id'];
            }
        }
        
        // Create new user if needed
        if (!$userId) {
            $userId = RedisManager::generateUserId();
            $token = RedisManager::generateToken();
            
            $userData = [
                'username' => $username,
                'avatar_url' => $avatarUrl,
                'token' => $token,
                'created_at' => time()
            ];
            
            RedisManager::registerUser($userId, $userData);
        }
        
        return [
            'user_id' => $userId,
            'token' => $token,
            'user_data' => $userData
        ];
    }
    
    /**
     * Get user's chats (placeholder for future implementation)
     */
    public function getUserChats(string $userId): array
    {
        // TODO: Implement chat loading logic
        return [];
    }
}