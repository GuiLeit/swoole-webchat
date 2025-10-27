<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Services\AuthService;
use App\Services\UserService;
use App\Services\RedisManager;

class AuthFlowTest extends TestCase
{
    public function testNewUserAuthenticationCreatesAndRegistersUser(): void
    {
        $auth = new AuthService();
        $username = 'alice';
        $avatar = 'https://example.com/a.png';

        $result = $auth->authenticate(
            username: $username,
            avatarUrl: $avatar,
            token: null
        );

        // Basic assertions
        $this->assertIsArray($result);
        $this->assertArrayHasKey('user_id', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user_data', $result);
        
        $this->assertNotEmpty($result['user_id']);
        $this->assertNotEmpty($result['token']);

        $userData = $result['user_data'];
        $this->assertSame($username, $userData['username']);
        $this->assertSame($avatar, $userData['avatar_url']);
        $this->assertSame($result['user_id'], $userData['id']);

        // Verify user persisted and token mapping exists
        $userService = new UserService();
        $userByToken = $userService->getUserByToken($result['token']);
        $this->assertNotNull($userByToken);
        $this->assertSame($result['user_id'], $userByToken->id);

        // Verify online presence
        $redis = RedisManager::getInstance();
        $this->assertTrue((bool)$redis->sIsMember('presence:online_users', $result['user_id']));
    }

    public function testExistingUserAuthenticationByIdReturnsExistingData(): void
    {
        $auth = new AuthService();
        $username = 'bob';
        $avatar = 'https://example.com/b.png';

        // First-time auth creates the user
        $first = $auth->authenticate(
            username: $username,
            avatarUrl: $avatar,
            token: null
        );

        $existingUserId = $first['user_id'];
        $existingToken = $first['token'];

        // Second-time auth using only userId (no token)
        $second = $auth->authenticate(
            username: $username,
            avatarUrl: $avatar,
            token: $existingToken
        );

        $this->assertSame($existingUserId, $second['user_id']);
        $this->assertSame($existingToken, $second['token']); // should keep same token
        $this->assertSame($username, $second['user_data']['username']);
        $this->assertSame($avatar, $second['user_data']['avatar_url']);

        // Verify token lookup works
        $userService = new UserService();
        $userByToken = $userService->getUserByToken($second['token']);
        $this->assertNotNull($userByToken);
        $this->assertSame($existingUserId, $userByToken->id);
    }
}
