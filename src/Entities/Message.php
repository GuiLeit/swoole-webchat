<?php

namespace App\Entities;

class Message
{
    public function __construct(
        public readonly string $id,
        public readonly string $senderId,
        public readonly ?string $chatId,
        public readonly string $content,
        public readonly string $type,
        public readonly int $timestamp,
    ) {}

    public static function create(
        string $senderId,
        ?string $chatId,
        string $content,
        string $type = 'text',
        ?int $timestamp = null
    ): self {
        $id = 'msg_' . bin2hex(random_bytes(16));
        return new self($id, $senderId, $chatId, $content, $type, $timestamp ?? time());
    }

    public static function fromRedisHash(string $id, array $hash): ?self
    {
        if (empty($hash)) {
            return null;
        }

        $senderId = $hash['sender_id'] ?? '';
        $content = $hash['content'] ?? '';
        
        if ($senderId === '' || $content === '') {
            return null;
        }

        $chatId = $hash['chat_id'] ?? null;
        $type = $hash['type'] ?? 'text';
        $timestamp = isset($hash['timestamp']) ? (int)$hash['timestamp'] : time();

        return new self($id, $senderId, $chatId, $content, $type, $timestamp);
    }

    public function toRedisHash(): array
    {
        return [
            'sender_id' => $this->senderId,
            'chat_id' => $this->chatId ?? '',
            'content' => $this->content,
            'type' => $this->type,
            'timestamp' => $this->timestamp,
        ];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sender_id' => $this->senderId,
            'chat_id' => $this->chatId,
            'content' => $this->content,
            'type' => $this->type,
            'timestamp' => $this->timestamp,
        ];
    }

    public function isValid(): bool
    {
        $allowedTypes = ['text', 'image', 'file', 'audio'];
        return !empty($this->content) && in_array($this->type, $allowedTypes);
    }
}
