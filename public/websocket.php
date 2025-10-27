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
date_default_timezone_set(getenv('TZ') ?: 'America/Sao_Paulo');

// ========================================
//               INITIALIZE SERVER
// ========================================
$server = new Server("0.0.0.0", 9501);
$server->set([
    'worker_num' => 1, // Use single worker to maintain shared state
    'heartbeat_check_interval' => 60,
    'heartbeat_idle_time' => 600,
]);

$websocketController = new WebsocketController($server);

// ========================================
//               SERVER EVENTS
// ========================================
$server->on('open', function (Server $server, Request $request) use ($websocketController) {
    $websocketController->handleOpen($request);
});

$server->on('message', function (Server $server, Frame $frame) use ($websocketController) {
    $websocketController->handleMessage($frame);
});

$server->on('close', function (Server $server, int $fd) use ($websocketController) {
    $websocketController->handleClose($fd);
});

$server->on('start', function (Server $server) {
    echo "🚀 Swoole WebSocket Server with HTTP API started!\n";
});

echo "🔄 Starting server...\n";
$server->start();