<?php

namespace App\Services;

use App\Repositories\MessageRepository;
use App\Entities\Chat;
use App\Entities\Message;

class MessageService
{
    private MessageRepository $messageRepository;
    private ChatService $chatService;

    public function __construct()
    {
        $this->messageRepository = new MessageRepository();
        $this->chatService = new ChatService();
    }

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

        // Ensure chat exists and user belongs to it
        if (!Chat::ensureUserBelongsToChat($chatId, $fromUserId)) { 
            throw new \InvalidArgumentException('User does not belong to the specified chat');
        }
        $this->chatService->ensureDmChat($chatId);

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

        // Persist message using repository (automatically limits to 100 messages)
        $this->messageRepository->storeMessage($message);
        $this->messageRepository->addMessageToChat($chatId, $message->id);
        
        // Update chat metadata
        $this->chatService->updateChatMeta($chatId, $message->content, $message->timestamp);

        $recipientId = Chat::getOtherUserInDm($chatId, $fromUserId);

        return [
            'chat_id' => $chatId,
            'message' => $message,
            'recipients' => [$recipientId],
        ];
    }

    /**
     * Get chat messages
     */
    public function getChatMessages(string $chatId, int $limit = null): array
    {
        return $this->messageRepository->getChatMessages($chatId, $limit);
    }

    /**
     * Get message count for a chat
     */
    public function getChatMessageCount(string $chatId): int
    {
        return $this->messageRepository->getChatMessageCount($chatId);
    }
}