<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Services\AuthService;
use App\Services\MessageService;
use App\Services\RedisManager;
use App\Entities\Chat;

class MessageDmFlowTest extends TestCase
{
    public function testDirectMessageFlowCreatesChatStoresMessageAndReturnsRecipient(): void
    {
        $auth = new AuthService();
        $messageService = new MessageService();

        // Create two users
        $u1 = $auth->authenticate(username: 'alice', avatarUrl: 'a.jpg');
        $u2 = $auth->authenticate(username: 'bob', avatarUrl: 'b.jpg');

        $user1Id = $u1['user_id'];
        $user2Id = $u2['user_id'];

        // Generate DM chat id
        $chatId = Chat::generateDmChatId($user1Id, $user2Id);

        // Send a message from user1 to user2
        $content = 'Hello Bob';
        $result = $messageService->sendMessage($user1Id, [
            'chatId' => $chatId,
            'content' => $content,
        ]);

        // Assert service return
        $this->assertSame($chatId, $result['chat_id']);
        $this->assertNotEmpty($result['message']->id);
        $this->assertSame($content, $result['message']->content);
        $this->assertSame($user1Id, $result['message']->senderId);
        $this->assertSame([$user2Id], $result['recipients']);

        // Assert Redis persistence
        $redis = RedisManager::getInstance();

        // Message stored
        $msgId = $result['message']->id;
        $stored = $redis->hGetAll("message:{$msgId}");
        $this->assertSame($content, $stored['content'] ?? null);
        $this->assertSame($user1Id, $stored['sender_id'] ?? null);
        $this->assertSame($chatId, $stored['chat_id'] ?? null);

        // Message indexed in chat list
        $list = $redis->lRange("chat:{$chatId}:messages", 0, -1);
        $this->assertContains($msgId, $list);

        // Chat metadata updated
        $meta = $redis->hGetAll("chat:{$chatId}:meta");
        $this->assertSame($content, $meta['last_message'] ?? null);
        $this->assertNotEmpty($meta['last_timestamp'] ?? null);
    }

    public function testChatMessagesListIsLimitedTo100(): void
    {
        $auth = new AuthService();
        $messageService = new MessageService();
        $redis = RedisManager::getInstance();

        // Users and chat
        $u1 = $auth->authenticate(username: 'charlie', avatarUrl: 'c.jpg');
        $u2 = $auth->authenticate(username: 'diana', avatarUrl: 'd.jpg');
        $user1Id = $u1['user_id'];
        $user2Id = $u2['user_id'];
        $chatId = Chat::generateDmChatId($user1Id, $user2Id);

        // Send 105 messages
        $ids = [];
        for ($i = 1; $i <= 105; $i++) {
            $res = $messageService->sendMessage($user1Id, [
                'chatId' => $chatId,
                'content' => "msg {$i}",
            ]);
            $ids[] = $res['message']->id;
        }

        // Only the last 100 should remain in the list
        $len = $redis->lLen("chat:{$chatId}:messages");
        $this->assertSame(100, $len);

        $list = $redis->lRange("chat:{$chatId}:messages", 0, -1);
        $expectedKept = array_slice($ids, -100);

        // Oldest 5 should be trimmed
        $trimmed = array_slice($ids, 0, 5);
        foreach ($trimmed as $oldId) {
            $this->assertNotContains($oldId, $list);
        }

        // The kept IDs should match the list content order (allowing Redis list order from lTrim)
        // Since we always rPush and trim to last 100, the list should equal the expectedKept (same order)
        $this->assertSame($expectedKept, $list);

        // Chat meta must reflect the last message
        $meta = $redis->hGetAll("chat:{$chatId}:meta");
        $this->assertSame('msg 105', $meta['last_message'] ?? null);
        $this->assertNotEmpty($meta['last_timestamp'] ?? null);
    }
}
