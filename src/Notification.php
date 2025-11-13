<?php
namespace MyApp;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Notification implements MessageComponentInterface {
    protected $clients;
    private $users = []; // Almacena resourceId => role

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        echo "Servidor de notificaciones iniciado...\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "Nueva conexión! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);

        // 1. Manejar el registro de rol del usuario
        if (isset($data['type']) && $data['type'] === 'register') {
            $this->users[$from->resourceId] = $data['role'];
            echo sprintf("Usuario %d se ha registrado como %s\n", $from->resourceId, $data['role']);
            return;
        }

        // 2. Manejar los mensajes de notificación
        if (isset($data['type']) && $data['type'] === 'notification') {
            $sender_role = $this->users[$from->resourceId] ?? null;
            if (!$sender_role) return; // No se puede procesar si el rol no está registrado

            echo sprintf("Notificación recibida de %d (rol: %s)\n", $from->resourceId, $sender_role);

            // --- LÓGICA DE DISTRIBUCIÓN BASADA EN ROLES ---

            // Caso A: Notificación de Enlace o Instructor para Admins
            if (in_array($sender_role, ['enlace', 'instructor'])) {
                echo " -> Es para Admins/Cap-DMMR. Reenviando...\n";
                foreach ($this->clients as $client) {
                    if ($from === $client) continue; // No enviar al remitente

                    $recipient_role = $this->users[$client->resourceId] ?? null;
                    if (in_array($recipient_role, ['administrador', 'cap-dmmr'])) {
                        $client->send($msg); // Reenviar el mensaje original completo
                        echo sprintf("    -> Enviado a %d (rol: %s)\n", $client->resourceId, $recipient_role);
                    }
                }
            }
            
            // Caso B: Notificación de Administrador para todos los demás
            elseif ($sender_role === 'administrador') {
                echo " -> Es de un Admin para todos. Reenviando...\n";
                foreach ($this->clients as $client) {
                    if ($from === $client) continue; // No enviar al remitente
                    $client->send($msg);
                    $recipient_role = $this->users[$client->resourceId] ?? 'desconocido';
                    echo sprintf("    -> Enviado a %d (rol: %s)\n", $client->resourceId, $recipient_role);
                }
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        if (isset($this->users[$conn->resourceId])) {
            unset($this->users[$conn->resourceId]);
        }
        $this->clients->detach($conn);
        echo "La conexión {$conn->resourceId} se ha desconectado\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Ha ocurrido un error en la conexión {$conn->resourceId}: {$e->getMessage()}\n";
        if (isset($this->users[$conn->resourceId])) {
            unset($this->users[$conn->resourceId]);
        }
        $conn->close();
    }
}
