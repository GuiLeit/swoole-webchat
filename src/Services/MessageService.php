<?php

namespace App\Services;

class MessageService
{
    /**
     * Send a message from one user to another or to a group
     */
    public function sendMessage(string $fromUserId, array $messageData): array
    {
        // Validate message data
        $this->validateMessageData($messageData);
        
        // TODO: Implement message sending logic
        // - Store message in database/Redis
        // - Determine recipients
        // - Return formatted message for broadcasting
        
        throw new \Exception('Message sending not yet implemented');
    }
    
    /**
     * Validate message data structure
     */
    private function validateMessageData(array $data): void
    {
        $required = ['content', 'type'];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new \InvalidArgumentException("Field '{$field}' is required");
            }
        }
        
        // Validate message type
        $allowedTypes = ['text', 'image', 'file', 'audio'];
        if (!in_array($data['type'], $allowedTypes)) {
            throw new \InvalidArgumentException('Invalid message type');
        }
    }
}