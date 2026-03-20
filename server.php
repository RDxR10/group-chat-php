#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/db.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Chat\ChatServer;

$port = (int) ($argv[1] ?? 8081);
$db   = get_db();

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChatServer($db)
        )
    ),
    $port
);

echo "WebSocket server listening on ws://localhost:{$port}\n";
echo "Press Ctrl+C to stop.\n";

$server->run();
