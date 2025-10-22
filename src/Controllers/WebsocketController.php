<?php

namespace App\Controllers;

use OpenSwoole\WebSocket\Server;
use OpenSwoole\Http\Request;
use OpenSwoole\WebSocket\Frame;

class WebsocketController
{
    private Server $server;

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
    }

    public function handleMessage(Frame $frame): void
    {
        $fd = $frame->fd;
        $data = $frame->data;
        echo "Received message from fd={$fd}: {$data}\n";
    }

    public function handleClose(int $fd): void
    {
        echo "Connection closed: fd={$fd}\n";
    }

}
