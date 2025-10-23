<?php

namespace App\Services;

use OpenSwoole\WebSocket\Server;
use App\Services\RedisManager;

class BroadcastService
{
    private Server $server;
    private array $connections;
    
    public function __construct(Server $server, array &$connections)
    {
        $this->server = $server;
        $this->connections = &$connections;
    }
    
    /**
     * Broadcast user joined event to all connected clients
     */
    public function broadcastUserJoined(string $userId, string $username, string $avatarUrl): void
    {
        $message = json_encode([
            'type' => 'user-joined',
            'user' => [
                'id' => $userId,
                'username' => $username,
                'avatar_url' => $avatarUrl
            ]
        ]);
        
        // Broadcast to all connected clients except the new user
        foreach ($this->connections as $fd => $connectedUserId) {
            if ($connectedUserId !== $userId && $this->server->isEstablished($fd)) {
                $this->server->push($fd, $message);
            }
        }
    }
    
    /**
     * Broadcast user left event to all connected clients
     */
    public function broadcastUserLeft(string $userId): void
    {
        $redis = RedisManager::getInstance();
        $userData = $redis->hGetAll("user:{$userId}");
        
        if (!$userData) {
            return;
        }
        
        $message = json_encode([
            'type' => 'user-left',
            'user' => [
                'id' => $userId,
                'username' => $userData['username'],
                'avatar_url' => $userData['avatar_url']
            ]
        ]);
        
        // Broadcast to all connected clients
        foreach ($this->connections as $fd => $connectedUserId) {
            if ($this->server->isEstablished($fd)) {
                $this->server->push($fd, $message);
            }
        }
    }
    
    /**
     * Send message to specific users
     */
    public function sendToUsers(array $userIds, array $message): void
    {
        $jsonMessage = json_encode($message);
        
        foreach ($this->connections as $fd => $connectedUserId) {
            if (in_array($connectedUserId, $userIds) && $this->server->isEstablished($fd)) {
                $this->server->push($fd, $jsonMessage);
            }
        }
    }
    
    /**
     * Send message to all connected users
     */
    public function broadcastToAll(array $message): void
    {
        $jsonMessage = json_encode($message);
        
        foreach ($this->connections as $fd => $connectedUserId) {
            if ($this->server->isEstablished($fd)) {
                $this->server->push($fd, $jsonMessage);
            }
        }
    }
}