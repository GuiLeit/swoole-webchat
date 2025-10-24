<?php

namespace App\Services;

use App\Entities\Chat;
use App\Entities\Message;

class MessageService
{
    /**
     * Send a message from one user to another or to a group
     * Returns: [ 'chat_id' => string, 'message' => Message, 'recipients' => string[] ]
     */
    public function sendMessage(string $fromUserId, array $messageData): array
    {
        // Basic validation
        $content = $messageData['content'] ?? '';
        if ($content === '') {
            throw new \InvalidArgumentException("Field 'content' is required");
        }

        $chatId = $messageData['chatId'] ?? null;
        if (!$chatId) {
            throw new \InvalidArgumentException("Field 'chatId' is required for direct messages");
        }

        $chatService = new ChatService();
        $redis = RedisManager::getInstance();

        // Ensure chat exists and get chat id
        if (Chat::ensureUserBelongsToChat($chatId, $fromUserId)) { 
            throw new \InvalidArgumentException('User does not belong to the specified chat');
        }
        $chatService->ensureDmChat($chatId);

        // Create message
        $message = Message::create(
            senderId: $fromUserId,
            chatId: $chatId,
            content: $content,
            type: 'text'
        );
        if (!$message->isValid()) {
            throw new \InvalidArgumentException('Invalid message data');
        }

        // Persist message and index in chat
        $redis->hMSet("message:{$message->id}", $message->toRedisHash());
        $redis->rPush("chat:{$chatId}:messages", $message->id);
        $redis->hMSet("chat:{$chatId}:meta", [
            'last_message' => $message->content,
            'last_timestamp' => $message->timestamp,
        ]);

        $recipientId = Chat::getOtherUserInDm($chatId, $fromUserId);

        return [
            'chat_id' => $chatId,
            'message' => $message,
            'recipients' => [$recipientId],
        ];
    }
}