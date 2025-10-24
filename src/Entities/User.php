<?php

namespace App\Entities;

class User
{
    public function __construct(
        public readonly string $id,
        public readonly string $username,
        public readonly string $avatar_url = '',
        public readonly string $token = '',
        public readonly int $created_at = 0,
    ) {
        // Ensure created_at is set
        if ($this->created_at === 0) {
            // Using reflection workaround since readonly: assign via local variable and property promotion already done
            // But since created_at is readonly, set default via parameter default in calls.
        }
    }

    public static function create(string $id, string $username, string $avatarUrl = '', string $token = '', ?int $createdAt = null): self
    {
        return new self($id, $username, $avatarUrl, $token, $createdAt ?? time());
    }

    public static function fromRedisHash(string $id, array $hash): ?self
    {
        if (empty($hash)) {
            return null;
        }
        $username = $hash['username'] ?? '';
        if ($username === '') {
            return null;
        }
        $avatar = $hash['avatar_url'] ?? '';
        $token = $hash['token'] ?? '';
        $createdAt = isset($hash['created_at']) ? (int)$hash['created_at'] : time();
        return new self($id, $username, $avatar, $token, $createdAt);
    }

    public function toRedisHash(): array
    {
        return [
            'username' => $this->username,
            'avatar_url' => $this->avatar_url,
            'token' => $this->token,
            'created_at' => $this->created_at,
        ];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'avatar_url' => $this->avatar_url,
            'token' => $this->token,
            'created_at' => $this->created_at,
        ];
    }
}
