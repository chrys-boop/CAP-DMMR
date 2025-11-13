<?php
// La ruta correcta al autoload.php, partiendo de la ubicación de este archivo.
require __DIR__ . '/vendor/autoload.php';

// --- INCLUSIÓN MANUAL PARA DEBUG ---
// Vamos a incluir la clase directamente para saltarnos el autoloader por ahora.
require __DIR__ . '/src/Notification.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

    // Se recomienda ejecutar el servidor en un puerto alto y no estándar
    $port = 8081;

    echo "Iniciando servidor WebSocket en el puerto $port...\n";

    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                // Usamos el Nombre de Clase Completamente Cualificado para evitar ambigüedades
                new \MyApp\Notification()
            )
        ),
        $port
    );

    $server->run();