<?php
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use MyApp\Chat;

require dirname(__DIR__) . '/vendor/autoload.php';

$chat = new Chat();

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            $chat
        )
    ),
    8080
);



$server->loop->addPeriodicTimer(60, function () use ($chat) {
    $chat->removeInactiveLobbies();
});

$server->run();