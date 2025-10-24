<?php

namespace App\Services;

use App\Services\RedisManager;
use App\Services\UserService;
use App\Entities\User;

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
        $user = null;
        $userService = new UserService();

        // Check if user has existing token
        if ($token) {
            $user = $userService->getUserByToken($token);
            if ($user) {
                $userId = $user->id;
            }
        }

        // Create new user if needed
        if (!$userId) {
            $userId = 'user_' . bin2hex(random_bytes(16));
            $token = bin2hex(random_bytes(32));

            $user = User::create($userId, $username, $avatarUrl, $token, time());
            $userService->registerUser($user);
        }

        return [
            'user_id' => $userId,
            'token' => $token,
            'user_data' => $user?->toArray()
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
