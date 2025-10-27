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
    public function authenticate(
        string $username,
        string $avatarUrl = '',
        ?string $token = null
    ): array {
        if (empty($username)) {
            throw new \InvalidArgumentException('Username is required');
        }

        $userService = new UserService();

        // Check if user has existing token
        if ($token) {
            $user = $userService->getUserByToken($token);
            if ($user) {
                return [
                    'user_id' => $user->id,
                    'token' => $user->token,
                    'user_data' => $user->toArray()
                ];
            }
        }

        // Create new user if needed
        $user = User::create(
            username: $username,
            avatarUrl: $avatarUrl,
            token: $token,
            createdAt: time()
        );
        $userService->registerUser($user);

        return [
            'user_id' => $user->id,
            'token' => $user->token,
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
