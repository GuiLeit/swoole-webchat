<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Entities\Chat;

class ChatTest extends TestCase
{
    public function testEnsureUserBelongsToChat_WithValidUserA(): void
    {
        $chatId = 'chat-user123-dm-user456';
        
        $result = Chat::ensureUserBelongsToChat($chatId, 'user123');
        
        $this->assertTrue($result);
    }

    public function testEnsureUserBelongsToChat_WithValidUserB(): void
    {
        $chatId = 'chat-user123-dm-user456';
        
        $result = Chat::ensureUserBelongsToChat($chatId, 'user456');
        
        $this->assertTrue($result);
    }

    public function testEnsureUserBelongsToChat_WithInvalidUser(): void
    {
        $chatId = 'chat-user123-dm-user456';
        
        $result = Chat::ensureUserBelongsToChat($chatId, 'user789');
        
        $this->assertFalse($result);
    }

    public function testEnsureUserBelongsToChat_WithMalformedChatId(): void
    {
        $chatId = 'invalid-chat-id';
        
        $result = Chat::ensureUserBelongsToChat($chatId, 'user123');
        
        $this->assertFalse($result);
    }

    public function testGetUsersByDmChatId_WithValidChatId(): void
    {
        $chatId = 'chat-user123-dm-user456';
        
        $users = Chat::getUsersByDmChatId($chatId);
        
        $this->assertCount(2, $users);
        $this->assertEquals('user123', $users[0]);
        $this->assertEquals('user456', $users[1]);
    }

    public function testGetUsersByDmChatId_WithInvalidChatId(): void
    {
        $chatId = 'invalid-chat-id';
        
        $users = Chat::getUsersByDmChatId($chatId);
        
        $this->assertEmpty($users);
    }

    public function testGetUsersByDmChatId_WithWrongFormat(): void
    {
        $chatId = 'chat-user123-group-user456';
        
        $users = Chat::getUsersByDmChatId($chatId);
        
        $this->assertEmpty($users);
    }

    public function testGetUsersByDmChatId_WithTooFewParts(): void
    {
        $chatId = 'chat-user123';
        
        $users = Chat::getUsersByDmChatId($chatId);
        
        $this->assertEmpty($users);
    }

    public function testGenerateDmChatId_InAlphabeticalOrder(): void
    {
        $chatId = Chat::generateDmChatId('alice', 'bob');
        
        $this->assertEquals('chat-alice-dm-bob', $chatId);
    }

    public function testGenerateDmChatId_InReverseOrder(): void
    {
        $chatId = Chat::generateDmChatId('bob', 'alice');
        
        // Should still be in alphabetical order
        $this->assertEquals('chat-alice-dm-bob', $chatId);
    }

    public function testGenerateDmChatId_ConsistencyBothWays(): void
    {
        $chatId1 = Chat::generateDmChatId('user123', 'user456');
        $chatId2 = Chat::generateDmChatId('user456', 'user123');
        
        $this->assertEquals($chatId1, $chatId2);
    }

    public function testGetOtherUserInDm_WithFirstUser(): void
    {
        $chatId = 'chat-user123-dm-user456';
        
        $otherUser = Chat::getOtherUserInDm($chatId, 'user123');
        
        $this->assertEquals('user456', $otherUser);
    }

    public function testGetOtherUserInDm_WithSecondUser(): void
    {
        $chatId = 'chat-user123-dm-user456';
        
        $otherUser = Chat::getOtherUserInDm($chatId, 'user456');
        
        $this->assertEquals('user123', $otherUser);
    }

    public function testGetOtherUserInDm_WithInvalidUser(): void
    {
        $chatId = 'chat-user123-dm-user456';
        
        $otherUser = Chat::getOtherUserInDm($chatId, 'user789');
        
        $this->assertNull($otherUser);
    }

    public function testGetOtherUserInDm_WithMalformedChatId(): void
    {
        $chatId = 'invalid-chat-id';
        
        $otherUser = Chat::getOtherUserInDm($chatId, 'user123');
        
        $this->assertNull($otherUser);
    }

    public function testCreateDmChat(): void
    {
        $chatId = 'chat-user123-dm-user456';
        $userA = 'user123';
        $userB = 'user456';
        
        $chat = Chat::createDmChat($chatId, $userA, $userB);
        
        $this->assertEquals($chatId, $chat->id);
        $this->assertEquals('dm', $chat->type);
        $this->assertEquals($userA, $chat->user_a);
        $this->assertEquals($userB, $chat->user_b);
        $this->assertGreaterThan(0, $chat->created_at);
        $this->assertGreaterThan(0, $chat->last_timestamp);
    }

    public function testFromRedisHash_WithValidData(): void
    {
        $id = 'chat-user123-dm-user456';
        $hash = [
            'type' => 'dm',
            'user_a' => 'user123',
            'user_b' => 'user456',
            'created_at' => '1234567890',
            'last_timestamp' => '1234567900',
        ];
        
        $chat = Chat::fromRedisHash($id, $hash);
        
        $this->assertNotNull($chat);
        $this->assertEquals($id, $chat->id);
        $this->assertEquals('dm', $chat->type);
        $this->assertEquals('user123', $chat->user_a);
        $this->assertEquals('user456', $chat->user_b);
        $this->assertEquals(1234567890, $chat->created_at);
        $this->assertEquals(1234567900, $chat->last_timestamp);
    }

    public function testFromRedisHash_WithEmptyHash(): void
    {
        $id = 'chat-user123-dm-user456';
        $hash = [];
        
        $chat = Chat::fromRedisHash($id, $hash);
        
        $this->assertNull($chat);
    }

    public function testFromRedisHash_WithMissingType(): void
    {
        $id = 'chat-user123-dm-user456';
        $hash = [
            'user_a' => 'user123',
            'user_b' => 'user456',
            'created_at' => '1234567890',
        ];
        
        $chat = Chat::fromRedisHash($id, $hash);
        
        $this->assertNull($chat);
    }

    public function testFromRedisHash_WithMissingUserA(): void
    {
        $id = 'chat-user123-dm-user456';
        $hash = [
            'type' => 'dm',
            'user_b' => 'user456',
            'created_at' => '1234567890',
        ];
        
        $chat = Chat::fromRedisHash($id, $hash);
        
        $this->assertNull($chat);
    }

    public function testFromRedisHash_WithMissingUserB(): void
    {
        $id = 'chat-user123-dm-user456';
        $hash = [
            'type' => 'dm',
            'user_a' => 'user123',
            'created_at' => '1234567890',
        ];
        
        $chat = Chat::fromRedisHash($id, $hash);
        
        $this->assertNull($chat);
    }

    public function testFromRedisHash_WithDefaultTimestamps(): void
    {
        $id = 'chat-user123-dm-user456';
        $hash = [
            'type' => 'dm',
            'user_a' => 'user123',
            'user_b' => 'user456',
        ];
        
        $beforeTime = time();
        $chat = Chat::fromRedisHash($id, $hash);
        $afterTime = time();
        
        $this->assertNotNull($chat);
        $this->assertGreaterThanOrEqual($beforeTime, $chat->created_at);
        $this->assertLessThanOrEqual($afterTime, $chat->created_at);
        $this->assertEquals(0, $chat->last_timestamp);
    }
}
