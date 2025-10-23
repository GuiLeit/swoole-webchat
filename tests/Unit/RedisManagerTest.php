<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
// use tests\TestCase;
use App\Services\RedisManager;

class RedisManagerTest extends TestCase
{
    public function testGenerateUserId(): void
    {
        $userId = RedisManager::generateUserId();
        
        $this->assertStringStartsWith('user_', $userId);
        $this->assertGreaterThan(10, strlen($userId));
    }

    public function testGenerateToken(): void
    {
        $token = RedisManager::generateToken();
        
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token);
    }

    public function testGenerateUniqueIds(): void
    {
        $id1 = RedisManager::generateUserId();
        $id2 = RedisManager::generateUserId();
        
        $this->assertNotEquals($id1, $id2);
    }

    public function testRegisterUser(): void
    {
        $userId = RedisManager::generateUserId();
        $token = RedisManager::generateToken();
        
        $userData = [
            'username' => 'testuser',
            'avatar_url' => 'https://example.com/avatar.jpg',
            'token' => $token,
            'created_at' => time()
        ];
        
        RedisManager::registerUser($userId, $userData);
        
        // Verify user was registered
        $redis = RedisManager::getInstance();
        $storedData = $redis->hGetAll("user:{$userId}");
        
        $this->assertEquals('testuser', $storedData['username']);
        $this->assertEquals($token, $storedData['token']);
        
        // Verify user is in online set
        $this->assertTrue($redis->sIsMember('presence:online_users', $userId));
    }

    public function testGetUserByToken(): void
    {
        $userId = RedisManager::generateUserId();
        $token = RedisManager::generateToken();
        
        $userData = [
            'username' => 'testuser',
            'avatar_url' => 'https://example.com/avatar.jpg',
            'token' => $token,
            'created_at' => time()
        ];
        
        RedisManager::registerUser($userId, $userData);
        
        // Retrieve user by token
        $retrievedUser = RedisManager::getUserByToken($token);
        
        $this->assertNotNull($retrievedUser);
        $this->assertEquals($userId, $retrievedUser['id']);
        $this->assertEquals('testuser', $retrievedUser['username']);
    }

    public function testGetUserByInvalidToken(): void
    {
        $result = RedisManager::getUserByToken('invalid_token');
        
        $this->assertNull($result);
    }

    public function testGetOnlineUsers(): void
    {
        // Register multiple users
        $user1Id = RedisManager::generateUserId();
        $user2Id = RedisManager::generateUserId();
        
        RedisManager::registerUser($user1Id, [
            'username' => 'user1',
            'avatar_url' => 'avatar1.jpg',
            'token' => RedisManager::generateToken()
        ]);
        
        RedisManager::registerUser($user2Id, [
            'username' => 'user2',
            'avatar_url' => 'avatar2.jpg',
            'token' => RedisManager::generateToken()
        ]);
        
        $onlineUsers = RedisManager::getOnlineUsers();
        
        $this->assertCount(2, $onlineUsers);
        $this->assertEquals('user1', $onlineUsers[0]['username']);
        $this->assertEquals('user2', $onlineUsers[1]['username']);
    }

    public function testRemoveUserFromPresence(): void
    {
        $userId = RedisManager::generateUserId();
        $userData = [
            'username' => 'testuser',
            'avatar_url' => 'avatar.jpg',
            'token' => RedisManager::generateToken()
        ];
        
        RedisManager::registerUser($userId, $userData);
        
        // Verify user is online
        $redis = RedisManager::getInstance();
        $this->assertTrue($redis->sIsMember('presence:online_users', $userId));
        
        // Remove user
        RedisManager::removeUserFromPresence($userId);
        
        // Verify user is removed
        $this->assertFalse($redis->sIsMember('presence:online_users', $userId));
    }
}