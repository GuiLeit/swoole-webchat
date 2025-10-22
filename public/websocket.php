<?php
/**
 * Swoole WebSocket server with HTTP routing that:
 * - Listens to Redis pub/sub channel chat:{chatId}
 * - Pushes new messages to connected clients in real-time
 * - Provides HTTP API endpoints
 */

use OpenSwoole\WebSocket\Server;
use OpenSwoole\WebSocket\Frame;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;

require __DIR__ . '/../vendor/autoload.php';

// ========================================
//               INITIALIZE SERVER
// ========================================
$server = new Server("0.0.0.0", 9501);
$server->set([
    'worker_num' => 1, // Use single worker to maintain shared state
    'heartbeat_check_interval' => 60,
    'heartbeat_idle_time' => 600,
]);


// ========================================
//               SERVER EVENTS
// ========================================
$server->on('open', function (Server $server, Request $request) use ($WebsocketController) {
    echo "New connection established: {$request->fd}\n";
});

$server->on('message', function (Server $server, Frame $frame) {
    echo "Received message from {$frame->fd}: {$frame->data}\n";
});

$server->on('close', function (int $fd) use ($WebsocketController) {
    echo "Connection closed: {$fd}\n";
});

$server->on('start', function (Server $server) {
    echo "🚀 Swoole WebSocket Server with HTTP API started!\n";
});

echo "🔄 Starting server...\n";
$server->start();