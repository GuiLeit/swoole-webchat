<?php

namespace App\Services;

use App\Entities\Chat;

class ChatService
{
	/**
	 * Ensure a DM chat exists between two users, linking it to both users.
	 * Returns chat ID.
	 */
	public function ensureDmChat(string $chatId): Chat
	{
		$redis = RedisManager::getInstance();
        $users = Chat::getUsersByDmChatId($chatId);
        if(empty($users)) {
            throw new \InvalidArgumentException('User does not belong to the specified chat');
        }

		$metaKey = "chat:{$chatId}:meta";
        if ($redis->exists($metaKey)) {
            $meta = $redis->hGetAll($metaKey);
            $chat = Chat::fromRedisHash($chatId, $meta);
            if (!$chat) {
                throw new \RuntimeException('Failed to load chat metadata');
            }
            return $chat;
        }
        
        $chat = Chat::createDmChat($chatId, $users[0], $users[1]);

        $redis->hMSet($metaKey, $chat->toRedisHash());

        $redis->sAdd("user:{$users[0]}:chats", $chatId);
        $redis->sAdd("user:{$users[1]}:chats", $chatId);

		return $chat;
	}
}