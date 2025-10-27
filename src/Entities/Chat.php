<?php

namespace App\Entities;

class Chat
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $user_a,
        public readonly string $user_b,
        public readonly int $created_at,
        public readonly int $last_timestamp = 0,
    ) {}

    public static function createDmChat(string $chatId, string $userA, string $userB): self
    {
        return new self(
            id: $chatId,
            type: 'dm',
            user_a: $userA,
            user_b: $userB,
            created_at: time(),
            last_timestamp: time(),
        );
    }

    public static function ensureUserBelongsToChat(string $chatId, string $userId): bool
    {
        $users = self::getUsersByDmChatId($chatId);
        return in_array($userId, $users, true);
    }

    public static function getUsersByDmChatId(string $chatId): array
    {
        $parts = explode('-', $chatId, 4);
        if (count($parts) === 4 && $parts[2] === 'dm') {
            return [$parts[1], $parts[3]];
        }

        return [];
    }

    public static function generateDmChatId(string $userA, string $userB): string
    {
        $pair = [$userA, $userB];
        sort($pair, SORT_STRING);
        return "chat-{$pair[0]}-dm-{$pair[1]}";
    }

    public static function getOtherUserInDm(string $chatId, string $userId): ?string
    {
        $users = self::getUsersByDmChatId($chatId);
        return $users[0] === $userId ? $users[1] : ($users[1] === $userId ? $users[0] : null);
    }

    public static function fromRedisHash(string $id, array $hash): ?self
    {
        if (empty($hash)) {
            return null;
        }

        $type = $hash['type'] ?? '';
        $userA = $hash['user_a'] ?? '';
        $userB = $hash['user_b'] ?? '';
        $createdAt = isset($hash['created_at']) ? (int)$hash['created_at'] : time();
        $lastTimestamp = isset($hash['last_timestamp']) ? (int)$hash['last_timestamp'] : 0;

        if ($type === '' || $userA === '' || $userB === '') {
            return null;
        }

        return new self($id, $type, $userA, $userB, $createdAt, $lastTimestamp);
    }


    public function toRedisHash(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'user_a' => $this->user_a,
            'user_b' => $this->user_b,
            'created_at' => $this->created_at,
            'last_message' => $this->last_message,
            'last_timestamp' => $this->last_timestamp,
        ];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'user_a' => $this->user_a,
            'user_b' => $this->user_b,
            'created_at' => $this->created_at,
            'last_message' => $this->last_message,
            'last_timestamp' => $this->last_timestamp,
        ];
    }
}
