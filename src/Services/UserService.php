<?php

namespace App\Services;

use App\Repositories\UserRepository;
use App\Entities\User;

class UserService
{
    private UserRepository $userRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
    }

    /**
     * Get user by ID
     */
    public function getUserById(string $userId): ?User
    {
        return $this->userRepository->getUserById($userId);
    }

    /**
     * Register a new user
     */
    public function registerUser(User $user): void
    {
        // Store user data
        $this->userRepository->storeUser($user);
        
        // Add to online users set
        $this->userRepository->addToOnlineUsers($user->id);
        
        // Set last seen
        $this->userRepository->setLastSeen($user->id, time());
        
        // Publish user joined event
        $this->userRepository->publishUserEvent('user_joined', [
            'id' => $user->id,
            'username' => $user->username,
            'avatar_url' => $user->avatar_url,
        ]);
    }

    /**
     * Get user by token
     */
    public function getUserByToken(string $token): ?User
    {
        return $this->userRepository->getUserByToken($token);
    }

    /**
     * Get all online users
     */
    public function getOnlineUsers(): array
    {
        return $this->userRepository->getAllOnlineUsers();
    }

    /**
     * Remove user from online presence
     */
    public function removeUserFromPresence(string $userId): void
    {
        // Remove from online users
        $this->userRepository->removeFromOnlineUsers($userId);
        
        // Update last seen
        $this->userRepository->setLastSeen($userId, time());
        
        // Publish user left event
        $user = $this->userRepository->getUserById($userId);
        if ($user) {
            $this->userRepository->publishUserEvent('user-left', [
                'id' => $userId,
                'username' => $user->username,
                'avatar_url' => $user->avatar_url,
            ]);
        }
    }
}
