<?php
//php .\bin\server.php
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use MyApp\Chat;

require dirname(__DIR__) . '/vendor/autoload.php';

$chat = new Chat();

$wsServer = new Ratchet\WebSocket\WsServer($chat);

$server = IoServer::factory(
    new HttpServer(
        $wsServer
    ),
    8080
);


$server->loop->addPeriodicTimer(60, function () use ($chat) {
    $chat->removeInactiveLobbies();
});

$server->loop->addPeriodicTimer(60, function () use ($chat) {
    $chat->heartbeat();
});


$server->run();