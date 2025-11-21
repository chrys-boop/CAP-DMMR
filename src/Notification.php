<?php
namespace MyApp;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Notification implements MessageComponentInterface {
    protected $clients;
    private $connectionUsers;
    private $userConnections;
    private $db; // <-- Para guardar la conexión a la BD

    // 1. El constructor ahora acepta la conexión PDO
    public function __construct(\PDO $db) {
        $this->clients = new \SplObjectStorage;
        $this->connectionUsers = new \SplObjectStorage;
        $this->userConnections = [];
        $this->db = $db;
        echo "Servidor de notificaciones v4.0 (Persistente) iniciado...\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "Nueva conexión! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        try {
            $data = json_decode($msg, true);
            if ($data === null) return;

            // --- REGISTRO DE USUARIO ---
            if (isset($data['type']) && $data['type'] === 'register' && isset($data['userId'])) {
                $userId = (int)$data['userId'];
                
                if (isset($this->userConnections[$userId])) {
                    $oldConn = $this->userConnections[$userId];
                    if ($oldConn !== $from) {
                         $this->clients->detach($oldConn);
                         $this->connectionUsers->detach($oldConn);
                    }
                }

                $this->connectionUsers[$from] = $userId;
                $this->userConnections[$userId] = $from;
                
                echo "Conexión {$from->resourceId} registrada para {$this->getUserIdForLog($userId)}\n";

                // 2. Al registrarse, enviar notificaciones pendientes
                $this->sendPendingNotifications($userId);
                return;
            }

            // --- ENVÍO DE NOTIFICACIONES ---
            if (isset($data['type']) && $data['type'] === 'notification' && isset($data['targetUserIds'])) {
                $senderId = $this->connectionUsers->contains($from) ? $this->connectionUsers[$from] : null;
                echo "Notificación recibida de {$this->getUserIdForLog($senderId)}. Destinatarios: " . implode(', ', $data['targetUserIds']) . "\n";

                $notificationPayload = json_encode($data);

                foreach ($data['targetUserIds'] as $userId) {
                    $userId = (int)$userId;
                    if (isset($this->userConnections[$userId])) {
                        // 3. Si el usuario está conectado, se envía directamente
                        $client = $this->userConnections[$userId];
                        $client->send($notificationPayload);
                        echo "   -> Enviado a {$this->getUserIdForLog($userId)} en la conexión {$client->resourceId}\n";
                    } else {
                        // 4. Si no, se guarda en la base de datos
                        $this->savePendingNotification($userId, $notificationPayload);
                        echo "   -> {$this->getUserIdForLog($userId)} no está conectado. Guardando notificación pendiente.\n";
                    }
                }
            }
        } catch (\Throwable $e) {
            echo "¡ERROR PROCESANDO MENSAJE!: {$e->getMessage()}\n";
        }
    }

    public function onClose(ConnectionInterface $conn) {
        if ($this->connectionUsers->contains($conn)) {
            $userId = $this->connectionUsers[$conn];
            if (isset($this->userConnections[$userId]) && $this->userConnections[$userId] === $conn) {
                unset($this->userConnections[$userId]);
            }
            $this->connectionUsers->detach($conn);
            echo "La conexión {$conn->resourceId} ({$this->getUserIdForLog($userId)}) se ha desconectado.\n";
        } else {
            echo "La conexión {$conn->resourceId} (no registrada) se ha desconectado.\n";
        }
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $userId = $this->connectionUsers->contains($conn) ? $this->connectionUsers[$conn] : null;
        echo "Ha ocurrido un error en la conexión {$conn->resourceId} ({$this->getUserIdForLog($userId)}): {$e->getMessage()}\n";
        $conn->close();
    }
    
    private function getUserIdForLog($userId) {
        if ($userId === null || $userId === '') return 'desconocido';
        return (int)$userId === 0 ? 'SYSTEM' : "Usuario {$userId}";
    }

    // --- NUEVAS FUNCIONES DE PERSISTENCIA ---

    private function savePendingNotification($userId, $payload) {
        try {
            $sql = "INSERT INTO pending_notifications (user_id, payload) VALUES (?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId, $payload]);
        } catch (\PDOException $e) {
            echo "¡ERROR DE BD al guardar notificación!: " . $e->getMessage() . "\n";
        }
    }

    private function sendPendingNotifications($userId) {
        // El sistema o usuarios no registrados no tienen notificaciones pendientes
        if ($userId === 0 || !isset($this->userConnections[$userId])) {
            return;
        }

        try {
            $sql_select = "SELECT id, payload FROM pending_notifications WHERE user_id = ? ORDER BY created_at ASC";
            $stmt_select = $this->db->prepare($sql_select);
            $stmt_select->execute([$userId]);
            $notifications = $stmt_select->fetchAll(\PDO::FETCH_ASSOC);

            if (count($notifications) > 0) {
                echo "Enviando " . count($notifications) . " notificaciones pendientes a {$this->getUserIdForLog($userId)}...\n";
                $client = $this->userConnections[$userId];
                $sent_ids = [];

                foreach ($notifications as $notification) {
                    $client->send($notification['payload']);
                    $sent_ids[] = $notification['id'];
                }

                // Borrar las notificaciones enviadas
                $id_placeholders = implode(',', array_fill(0, count($sent_ids), '?'));
                $sql_delete = "DELETE FROM pending_notifications WHERE id IN ($id_placeholders)";
                $stmt_delete = $this->db->prepare($sql_delete);
                $stmt_delete->execute($sent_ids);
            }
        } catch (\PDOException $e) {
            echo "¡ERROR DE BD al enviar pendientes!: " . $e->getMessage() . "\n";
        }
    }
}
