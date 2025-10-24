<?php

namespace App\Services;

use OpenSwoole\WebSocket\Server;
use App\Services\RedisManager;
use App\Services\UserService;

class ConnectionService
{
    private Server $server;
    private array $connections;
    
    public function __construct(Server $server, array &$connections)
    {
        $this->server = $server;
        $this->connections = &$connections;
    }
    
    /**
     * Register a new connection
     */
    public function registerConnection(int $fd, string $userId): void
    {
        $this->connections[$fd] = $userId;
        
        // Add user to Redis presence
        RedisManager::getInstance()->sAdd('presence:online_users', $userId);
    }
    
    /**
     * Remove connection and handle cleanup
     */
    public function removeConnection(int $fd): ?string
    {
        if (!isset($this->connections[$fd])) {
            return null;
        }
        
        $userId = $this->connections[$fd];
        
        // Remove user from presence
        (new UserService())->removeUserFromPresence($userId);
        unset($this->connections[$fd]);
        
        return $userId;
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(int $fd): bool
    {
        return isset($this->connections[$fd]);
    }
    
    /**
     * Get user ID by file descriptor
     */
    public function getUserId(int $fd): ?string
    {
        return $this->connections[$fd] ?? null;
    }
    

    /**
     * Send authentication error message to specific connection
     */
    public function sendAuthError(int $fd, string $message): void
    {
        if ($this->server->isEstablished($fd)) {
            $this->server->push($fd, json_encode([
                'type' => 'auth-error',
                'message' => $message
            ]));
        }
    }
    
    /**
     * Send error message to specific connection
     */
    public function sendError(int $fd, string $message): void
    {
        if ($this->server->isEstablished($fd)) {
            $this->server->push($fd, json_encode([
                'type' => 'error',
                'message' => $message
            ]));
        }
    }
    
    /**
     * Send success response to specific connection
     */
    public function sendResponse(int $fd, array $data): void
    {
        if ($this->server->isEstablished($fd)) {
            $this->server->push($fd, json_encode($data));
        }
    }
}