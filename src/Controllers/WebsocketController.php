<?php

namespace App\Controllers;

use OpenSwoole\WebSocket\Server;
use OpenSwoole\Http\Request;
use OpenSwoole\WebSocket\Frame;
use App\Services\RedisManager;

class WebsocketController
{
    private Server $server;
    private array $connections = []; // fd => user_id mapping

    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    // ========================================
    //               EVENT HANDLERS
    // ========================================
    public function handleOpen(Request $request): void
    {
        $fd = $request->fd;
        echo "Connection opened: fd={$fd}\n";
        
        // Send welcome message
        $this->server->push($fd, json_encode([
            'type' => 'welcome',
            'message' => 'Connected to WebSocket server'
        ]));
    }

    public function handleMessage(Frame $frame): void
    {
        $fd = $frame->fd;
        $data = $frame->data;
        echo "Received message from fd={$fd}: {$data}\n";
        
        try {
            $message = json_decode($data, true);
            
            if (!$message || !isset($message['action'])) {
                $this->sendError($fd, 'Invalid message format');
                return;
            }
            
            switch ($message['action']) {
                case 'auth':
                    $this->handleAuth($fd, $message['data'] ?? []);
                    break;
                    
                case 'send_message':
                    $this->handleSendMessage($fd, $message['data'] ?? []);
                    break;
                    
                default:
                    $this->sendError($fd, 'Unknown action: ' . $message['action']);
            }
            
        } catch (\Exception $e) {
            echo "Error handling message: " . $e->getMessage() . "\n";
            $this->sendError($fd, 'Server error');
        }
    }

    public function handleClose(int $fd): void
    {
        echo "Connection closed: fd={$fd}\n";
        
        // Remove user from presence if authenticated
        if (isset($this->connections[$fd])) {
            $userId = $this->connections[$fd];
            RedisManager::removeUserFromPresence($userId);
            unset($this->connections[$fd]);
            
            // Notify other users
            $this->broadcastUserLeft($userId);
        }
    }
    
    // ========================================
    //               AUTH HANDLERS
    // ========================================
    private function handleAuth(int $fd, array $data): void
    {
        $username = $data['username'] ?? '';
        $avatarUrl = $data['avatar_url'] ?? '';
        $token = $data['token'] ?? null;
        
        if (empty($username)) {
            $this->sendError($fd, 'Username is required');
            return;
        }
        
        $userId = null;
        $userData = null;
        
        // Check if user has existing token
        if ($token) {
            $userData = RedisManager::getUserByToken($token);
            if ($userData) {
                $userId = $userData['id'];
            }
        }
        
        // Create new user if needed
        if (!$userId) {
            $userId = RedisManager::generateUserId();
            $token = RedisManager::generateToken();
            
            $userData = [
                'username' => $username,
                'avatar_url' => $avatarUrl,
                'token' => $token,
                'created_at' => time()
            ];
            
            RedisManager::registerUser($userId, $userData);
        }
        
        // Store connection mapping
        $this->connections[$fd] = $userId;
        
        // Send auth success response
        $this->server->push($fd, json_encode([
            'type' => 'auth_ok',
            'token' => $token,
            'user_id' => $userId,
            'chats' => [] // TODO: Load user's chats
        ]));
        
        // Send current online users list
        $onlineUsers = RedisManager::getOnlineUsers();
        $this->server->push($fd, json_encode([
            'type' => 'users-list',
            'users' => $onlineUsers
        ]));
        
        // Broadcast new user joined to others
        $this->broadcastUserJoined($userId, $username, $avatarUrl);
    }
    
    // ========================================
    //               MESSAGE HANDLERS
    // ========================================
    private function handleSendMessage(int $fd, array $data): void
    {
        // Check if user is authenticated
        if (!isset($this->connections[$fd])) {
            $this->sendError($fd, 'Not authenticated');
            return;
        }
        
        // TODO: Implement message sending logic
    }
    
    // ========================================
    //               BROADCAST METHODS
    // ========================================
    private function broadcastUserJoined(string $userId, string $username, string $avatarUrl): void
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
    
    private function broadcastUserLeft(string $userId): void
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
    
    // ========================================
    //               UTILITY METHODS
    // ========================================
    private function sendError(int $fd, string $message): void
    {
        if ($this->server->isEstablished($fd)) {
            $this->server->push($fd, json_encode([
                'type' => 'error',
                'message' => $message
            ]));
        }
    }
}