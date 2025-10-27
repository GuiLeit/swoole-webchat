<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\UserService;
use App\Services\RedisManager;
use App\Entities\User;

class UserServiceTest extends TestCase
{
    private function makeUserId(): string
    {
        return 'user_' . bin2hex(random_bytes(8));
    }

    private function makeToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function testRegisterUser(): void
    {
        $service = new UserService();
        $userId = $this->makeUserId();
        $token = $this->makeToken();

        $user = User::create($userId, 'testuser', 'https://example.com/avatar.jpg', $token, time());

        $service->registerUser($user);

        $redis = RedisManager::getInstance();
        $storedData = $redis->hGetAll("user:{$userId}");

        $this->assertEquals('testuser', $storedData['username']);
        $this->assertEquals($token, $storedData['token']);
        $this->assertTrue($redis->sIsMember('presence:online_users', $userId));
    }

    public function testGetUserByToken(): void
    {
        $service = new UserService();
        $userId = $this->makeUserId();
        $token = $this->makeToken();

        $user = User::create($userId, 'testuser', 'https://example.com/avatar.jpg', $token, time());
        $service->registerUser($user);

        $retrievedUser = $service->getUserByToken($token);
        $this->assertNotNull($retrievedUser);
        $this->assertEquals($userId, $retrievedUser->id);
        $this->assertEquals('testuser', $retrievedUser->username);
    }

    public function testGetUserByInvalidToken(): void
    {
        $service = new UserService();
        $result = $service->getUserByToken('invalid_token');
        $this->assertNull($result);
    }

    public function testGetOnlineUsers(): void
    {
        $service = new UserService();

        $user1Id = $this->makeUserId();
        $service->registerUser(User::create($user1Id, 'user1', 'avatar1.jpg', $this->makeToken(), time()));

        $user2Id = $this->makeUserId();
        $service->registerUser(User::create($user2Id, 'user2', 'avatar2.jpg', $this->makeToken(), time()));

        $onlineUsers = $service->getOnlineUsers();
        $this->assertGreaterThanOrEqual(2, count($onlineUsers));
        $usernames = array_map(fn($u) => $u->username, $onlineUsers);
        $this->assertContains('user1', $usernames);
        $this->assertContains('user2', $usernames);
    }

    public function testRemoveUserFromPresence(): void
    {
        $service = new UserService();

        $userId = $this->makeUserId();
        $service->registerUser(User::create($userId, 'testuser', 'avatar.jpg', $this->makeToken(), time()));

        $redis = RedisManager::getInstance();
        $this->assertTrue($redis->sIsMember('presence:online_users', $userId));

        $service->removeUserFromPresence($userId);
        $this->assertFalse($redis->sIsMember('presence:online_users', $userId));
    }
}
