<?php

namespace App\Controllers;

use OpenSwoole\WebSocket\Server;
use OpenSwoole\Http\Request;
use OpenSwoole\WebSocket\Frame;
use App\Services\RedisManager;
use App\Services\AuthService;
use App\Services\MessageService;
use App\Services\BroadcastService;
use App\Services\ConnectionService;

class WebsocketController
{
    private Server $server;
    private array $connections = []; // fd => user_id mapping
    
    private AuthService $authService;
    private MessageService $messageService;
    private BroadcastService $broadcastService;
    private ConnectionService $connectionService;

    public function __construct(Server $server)
    {
        $this->server = $server;
        
        // Initialize services
        $this->authService = new AuthService();
        $this->messageService = new MessageService();
        $this->broadcastService = new BroadcastService($server, $this->connections);
        $this->connectionService = new ConnectionService($server, $this->connections);
    }

    // ====
    //    SWOOLE EVENT HANDLERS
    // ====
    public function handleOpen(Request $request): void
    {
        $fd = $request->fd;
        echo "Connection opened: fd={$fd}\n";
        
        // Send welcome message
        $this->connectionService->sendResponse($fd, [
            'type' => 'welcome',
            'message' => 'Connected to WebSocket server'
        ]);
    }

    public function handleMessage(Frame $frame): void
    {
        $fd = $frame->fd;
        $data = $frame->data;
        echo "Received message from fd={$fd}: {$data}\n";
        
        try {
            $message = json_decode($data, true);
            
            if (!$message || !isset($message['action'])) {
                $this->connectionService->sendError($fd, 'Invalid message format');
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
                    $this->connectionService->sendError($fd, 'Unknown action: ' . $message['action']);
            }
            
        } catch (\Exception $e) {
            echo "Error handling message: " . $e->getMessage() . "\n";
            $this->connectionService->sendError($fd, 'Server error');
        }
    }

    public function handleClose(int $fd): void
    {
        echo "Connection closed: fd={$fd}\n";
        
        $userId = $this->connectionService->removeConnection($fd);
        
        if ($userId) {
            // Notify other users
            $this->broadcastService->broadcastUserLeft($userId);
        }
    }
    
    // ====
    //    ACTION HANDLERS (Delegating to services)
    // ====
    private function handleAuth(int $fd, array $data): void
    {
        try {
            $username = $data['username'] ?? '';
            $avatarUrl = $data['avatar_url'] ?? '';
            $token = $data['token'] ?? null;
            
            // Authenticate user through service
            $authResult = $this->authService->authenticate(
                username: $username, 
                avatarUrl: $avatarUrl, 
                token: $token
            );
            
            // Register connection
            $this->connectionService->registerConnection($fd, $authResult['user_id']);
            
            // Send auth success response
            $this->connectionService->sendResponse($fd, [
                'type' => 'auth_ok',
                'token' => $authResult['token'],
                'user_id' => $authResult['user_id'],
                'chats' => $this->authService->getUserChats($authResult['user_id'])
            ]);
            
            // Send current online users list
            $onlineUsers = RedisManager::getOnlineUsers();
            $this->connectionService->sendResponse($fd, [
                'type' => 'users-list',
                'users' => $onlineUsers
            ]);
            
            // Broadcast new user joined to others
            $userData = $authResult['user_data'];
            $this->broadcastService->broadcastUserJoined(
                $authResult['user_id'], 
                $userData['username'], 
                $userData['avatar_url']
            );
            
        } catch (\Exception $e) {
            $this->connectionService->sendError($fd, $e->getMessage());
        }
    }
    
    private function handleSendMessage(int $fd, array $data): void
    {
        // Check if user is authenticated
        if (!$this->connectionService->isAuthenticated($fd)) {
            $this->connectionService->sendError($fd, 'Not authenticated');
            return;
        }
        
        try {
            $userId = $this->connectionService->getUserId($fd);
            $message = $this->messageService->sendMessage($userId, $data);
            
            // TODO: Broadcast message to recipients
            
        } catch (\Exception $e) {
            $this->connectionService->sendError($fd, $e->getMessage());
        }
    }
}