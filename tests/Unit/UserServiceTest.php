<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\UserService;
use App\Services\RedisManager;
use App\Entities\User;

class UserServiceTest extends TestCase
{
    public function testRegisterUser(): void
    {
        $service = new UserService();

        $user = User::create('testuser', 'https://example.com/avatar.jpg');

        $service->registerUser($user);

        $redis = RedisManager::getInstance();
        $storedData = $redis->hGetAll("user:{$user->id}");

        $this->assertEquals('testuser', $storedData['username']);
        $this->assertEquals($user->token, $storedData['token']);
        $this->assertTrue($redis->sIsMember('presence:online_users', $user->id));
    }

    public function testGetUserByToken(): void
    {
        $service = new UserService();

        $user = User::create('testuser', 'https://example.com/avatar.jpg');
        $service->registerUser($user);

        $retrievedUser = $service->getUserByToken($user->token);
        $this->assertNotNull($retrievedUser);
        $this->assertEquals($user->id, $retrievedUser->id);
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

        $service->registerUser(User::create('user1', 'avatar1.jpg'));
        $service->registerUser(User::create('user2', 'avatar2.jpg'));

        $onlineUsers = $service->getOnlineUsers();
        $this->assertGreaterThanOrEqual(2, count($onlineUsers));
        $usernames = array_map(fn($u) => $u->username, $onlineUsers);
        $this->assertContains('user1', $usernames);
        $this->assertContains('user2', $usernames);
    }

    public function testRemoveUserFromPresence(): void
    {
        $service = new UserService();
        $user = User::create('testuser', 'avatar.jpg');
        $service->registerUser($user);

        $redis = RedisManager::getInstance();
        $this->assertTrue($redis->sIsMember('presence:online_users', $user->id));

        $service->removeUserFromPresence($user->id);
        $this->assertFalse($redis->sIsMember('presence:online_users', $user->id));
    }
}
