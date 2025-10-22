<?php
/**
 * Swoole WebSocket server with HTTP routing that:
 * - Listens to Redis pub/sub channel chat:{chatId}
 * - Pushes new messages to connected clients in real-time
 * - Provides HTTP API endpoints
 */

use App\Controllers\WebsocketController;
use OpenSwoole\WebSocket\Server;
use OpenSwoole\WebSocket\Frame;
use OpenSwoole\Http\Request;

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

$WebsocketController = new WebsocketController($server);

// ========================================
//               SERVER EVENTS
// ========================================
$server->on('open', function (Server $server, Request $request) use ($WebsocketController) {
    $WebsocketController->handleOpen($request);
});

$server->on('message', function (Server $server, Frame $frame) use ($WebsocketController) {
    $WebsocketController->handleMessage($frame);
});

$server->on('close', function (int $fd) use ($WebsocketController) {
    $WebsocketController->handleClose($fd);
});

$server->on('start', function (Server $server) {
    echo "🚀 Swoole WebSocket Server with HTTP API started!\n";
});

echo "🔄 Starting server...\n";
$server->start();