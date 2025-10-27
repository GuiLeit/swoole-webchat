<?php

namespace App\Services;

use App\Repositories\ChatRepository;
use App\Entities\Chat;

class ChatService
{
	private ChatRepository $chatRepository;

	public function __construct()
	{
		$this->chatRepository = new ChatRepository();
	}

	/**
	 * Ensure a DM chat exists between two users, linking it to both users.
	 * Returns chat ID.
	 */
	public function ensureDmChat(string $chatId): Chat
	{
        $users = Chat::getUsersByDmChatId($chatId);
        if(empty($users)) {
            throw new \InvalidArgumentException('User does not belong to the specified chat');
        }

		// Check if chat already exists
		$chat = $this->chatRepository->getChatById($chatId);
		if ($chat) {
			return $chat;
		}
        
		// Create new chat
        $chat = Chat::createDmChat($chatId, $users[0], $users[1]);

		// Store chat metadata
        $this->chatRepository->storeChat($chat);

		// Link chat to both users
        $this->chatRepository->addChatToUser($users[0], $chatId);
        $this->chatRepository->addChatToUser($users[1], $chatId);

		return $chat;
	}

	/**
	 * Get all chats for a user
	 */
	public function getUserChats(string $userId): array
	{
		return $this->chatRepository->getUserChats($userId);
	}

	/**
	 * Update chat metadata (last message, timestamp)
	 */
	public function updateChatMeta(string $chatId, string $lastMessage, int $timestamp): void
	{
		$this->chatRepository->updateChatMeta($chatId, [
			'last_message' => $lastMessage,
			'last_timestamp' => $timestamp,
		]);
	}
}