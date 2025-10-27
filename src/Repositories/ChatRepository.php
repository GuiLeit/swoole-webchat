<?php

namespace App\Repositories;

use App\Services\RedisManager;
use App\Entities\Chat;

class ChatRepository
{
    private \Redis $redis;

    public function __construct()
    {
        $this->redis = RedisManager::getInstance();
    }

    /**
     * Store chat metadata in Redis
     */
    public function storeChat(Chat $chat): void
    {
        $this->redis->hMSet("chat:{$chat->id}:meta", $chat->toRedisHash());
    }

    /**
     * Get chat by ID
     */
    public function getChatById(string $chatId): ?Chat
    {
        $metaKey = "chat:{$chatId}:meta";
        
        if (!$this->redis->exists($metaKey)) {
            return null;
        }
        
        $meta = $this->redis->hGetAll($metaKey);
        return Chat::fromRedisHash($chatId, $meta);
    }

    /**
     * Update chat metadata (last message, timestamp)
     */
    public function updateChatMeta(string $chatId, array $data): void
    {
        $this->redis->hMSet("chat:{$chatId}:meta", $data);
    }

    /**
     * Add chat to user's chat list
     */
    public function addChatToUser(string $userId, string $chatId): void
    {
        $this->redis->sAdd("user:{$userId}:chats", $chatId);
    }

    /**
     * Get all chat IDs for a user
     */
    public function getUserChatIds(string $userId): array
    {
        return $this->redis->sMembers("user:{$userId}:chats");
    }

    /**
     * Get all chats for a user (with full metadata)
     */
    public function getUserChats(string $userId): array
    {
        $chatIds = $this->getUserChatIds($userId);
        $chats = [];
        
        foreach ($chatIds as $chatId) {
            $chat = $this->getChatById($chatId);
            if ($chat) {
                $chats[] = $chat;
            }
        }
        
        return $chats;
    }

    /**
     * Check if a chat exists
     */
    public function chatExists(string $chatId): bool
    {
        return $this->redis->exists("chat:{$chatId}:meta") > 0;
    }
}
