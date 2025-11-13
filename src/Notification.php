<?php
namespace MyApp;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Notification implements MessageComponentInterface {
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        echo "Servidor de notificaciones iniciado...\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        // Almacenar la nueva conexión para enviar mensajes más tarde
        $this->clients->attach($conn);
        echo "Nueva conexión! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        echo sprintf('Recibido mensaje "%s" desde la conexión %d \n', $msg, $from->resourceId);

        // El mensaje recibido se reenvía a todos los demás clientes
        foreach ($this->clients as $client) {
            // El remitente no necesita recibir su propio mensaje
            if ($from !== $client) {
                $client->send($msg);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        // La conexión se ha cerrado, la eliminamos
        $this->clients->detach($conn);
        echo "La conexión {$conn->resourceId} se ha desconectado\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Ha ocurrido un error: {$e->getMessage()}\n";
        $conn->close();
    }
}
