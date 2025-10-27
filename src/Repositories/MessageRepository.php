<?php

namespace App\Repositories;

use App\Services\RedisManager;
use App\Entities\Message;

class MessageRepository
{
    private \Redis $redis;
    private const MAX_MESSAGES_PER_CHAT = 100;

    public function __construct()
    {
        $this->redis = RedisManager::getInstance();
    }

    /**
     * Store message in Redis
     */
    public function storeMessage(Message $message): void
    {
        $this->redis->hMSet("message:{$message->id}", $message->toRedisHash());
    }

    /**
     * Get message by ID
     */
    public function getMessageById(string $messageId): ?Message
    {
        $messageData = $this->redis->hGetAll("message:{$messageId}");
        return Message::fromRedisHash($messageId, $messageData);
    }

    /**
     * Add message to chat's message list and maintain limit
     * Only keeps the last MAX_MESSAGES_PER_CHAT messages
     */
    public function addMessageToChat(string $chatId, string $messageId): void
    {
        $listKey = "chat:{$chatId}:messages";
        
        // Add message to list
        $this->redis->rPush($listKey, $messageId);
        
        // Trim to keep only last MAX_MESSAGES_PER_CHAT messages
        $this->redis->lTrim($listKey, -self::MAX_MESSAGES_PER_CHAT, -1);
    }

    /**
     * Get chat messages (limited to last MAX_MESSAGES_PER_CHAT)
     */
    public function getChatMessages(string $chatId, int $limit = null): array
    {
        $limit = $limit ?? self::MAX_MESSAGES_PER_CHAT;
        $messageIds = $this->redis->lRange("chat:{$chatId}:messages", -$limit, -1);
        
        $messages = [];
        foreach ($messageIds as $messageId) {
            $message = $this->getMessageById($messageId);
            if ($message) {
                $messages[] = $message;
            }
        }
        
        return $messages;
    }

    /**
     * Get the count of messages in a chat
     */
    public function getChatMessageCount(string $chatId): int
    {
        return $this->redis->lLen("chat:{$chatId}:messages");
    }

    /**
     * Delete old messages that are no longer in the chat's message list
     * This is a cleanup method to remove orphaned message hashes
     */
    public function cleanupOrphanedMessages(string $chatId): int
    {
        // This would require tracking all message IDs ever created
        // For now, we rely on Redis eviction policy to handle this
        // Could be implemented with a background job if needed
        return 0;
    }
}
