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
        if (isset($data['type']) && $data['type'] === 'notification' && isset($data['payload'])) {
            $sender_role = $this->users[$from->resourceId] ?? null;
            if (!$sender_role) return; // No se puede procesar si el rol no está registrado

            $payload = $data['payload'];
            echo sprintf("Notificación '%s' recibida de %d (rol: %s)\n", $payload['type'], $from->resourceId, $sender_role);

            // --- LÓGICA DE DISTRIBUCIÓN MEJORADA ---

            // Caso A: Notificación de nuevo mensaje de chat. ¡Enviar a todos!
            if ($payload['type'] === 'new_chat_message') {
                echo " -> Es un mensaje de chat para todos. Reenviando...\n";
                foreach ($this->clients as $client) {
                    if ($from !== $client) { // No enviar al remitente original
                        $client->send($msg); // Reenviar el mensaje original completo
                        $recipient_role = $this->users[$client->resourceId] ?? 'desconocido';
                        echo sprintf("    -> Enviado a %d (rol: %s)\n", $client->resourceId, $recipient_role);
                    }
                }
            }
            
            // Caso B: Notificación de carga de oficio (Enlace/Instructor) para Admins
            elseif ($payload['type'] === 'new_upload' && in_array($sender_role, ['enlace', 'instructor', 3, 2])) {
                echo " -> Es una carga de oficio para Admins/Cap-DMMR. Reenviando...\n";
                foreach ($this->clients as $client) {
                    if ($from === $client) continue;
                    $recipient_role = $this->users[$client->resourceId] ?? null;
                    if (in_array($recipient_role, ['administrador', 'cap-dmmr', 5, 4])) {
                        $client->send($msg);
                        echo sprintf("    -> Enviado a %d (rol: %s)\n", $client->resourceId, $recipient_role);
                    }
                }
            }
            
            // Caso C: Notificación de Administrador (ej. nuevo manual) para todos los demás
            elseif ($payload['type'] === 'new_manual' && in_array($sender_role, ['administrador', 5])) {
                echo " -> Es de un Admin para todos. Reenviando...\n";
                foreach ($this->clients as $client) {
                    if ($from === $client) continue;
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
