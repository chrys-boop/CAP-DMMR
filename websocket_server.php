<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/Notification.php';
require __DIR__ . '/db_connection.php'; // <-- 1. INCLUIMOS LA CONEXIÓN A BD

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

// --- INICIO DEL SERVIDOR ---

$port = 8081;
echo "Iniciando servidor WebSocket en el puerto $port...\n";

// 2. CREAMOS UNA INSTANCIA DE NOTIFICATION Y LE PASAMOS LA CONEXIÓN
$notification_app = new \MyApp\Notification($conn);

// 3. INYECTAMOS LA APP CON CONEXIÓN AL SERVIDOR
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            $notification_app
        )
    ),
    $port
);

$server->run();
