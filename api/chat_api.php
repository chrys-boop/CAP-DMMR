<?php
// Mostrar errores en desarrollo
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- Dependencias y Configuración Inicial ---
require_once __DIR__ . '/../vendor/autoload.php'; // Para el cliente WebSocket

// Convertir errores a excepciones para un manejo centralizado
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return false;
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {
    header('Content-Type: application/json; charset=utf-8');

    session_start();
    require_once __DIR__ . '/../db_connection.php';

    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Usuario no autenticado');
    }

    $current_user_id = $_SESSION['user_id'];
    $current_user_role = $_SESSION['user_role'] ?? null;
    $current_user_name = $_SESSION['user_nombre'] ?? 'Usuario'; // <--- OBTENER NOMBRE DE USUARIO
    $action = $_REQUEST['action'] ?? null;

    // ---------- Helpers ----------

    // +++ NUEVA FUNCIÓN PARA NOTIFICAR +++
    function notify_websocket($payload) {
        try {
            $client = new \WebSocket\Client("ws://127.0.0.1:8081");
            $client->send(json_encode($payload));
            $client->close();
        } catch (\Throwable $e) {
            error_log("Error de notificación WebSocket: " . $e->getMessage());
        }
    }

    function get_or_create_general_chat_id($conn, $admin_id = 1) {
        $sql_find = "SELECT id FROM chat_conversations WHERE type = 'group' AND name = 'General' LIMIT 1";
        $stmt = $conn->query($sql_find);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row['id'];
        }

        $conn->beginTransaction();
        try {
            $sql_create = "INSERT INTO chat_conversations (name, type, created_by) VALUES ('General', 'group', ?)";
            $stmt_create = $conn->prepare($sql_create);
            $stmt_create->execute([$admin_id]);
            $conversation_id = $conn->lastInsertId();

            $stmt_users = $conn->query("SELECT id FROM usuarios");
            $all_user_ids = $stmt_users->fetchAll(PDO::FETCH_COLUMN);

            if(!empty($all_user_ids)) {
                $placeholders = implode(', ', array_fill(0, count($all_user_ids), '(?, ?)'));
                $params = [];
                foreach ($all_user_ids as $user_id) {
                    $params[] = $conversation_id;
                    $params[] = $user_id;
                }
                $stmt_part = $conn->prepare("INSERT IGNORE INTO chat_participants (conversation_id, user_id) VALUES $placeholders");
                $stmt_part->execute($params);
            }
            $conn->commit();
            return $conversation_id;
        } catch (Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    // ---------- ACTIONS ----------
    function get_conversations($conn, $user_id, $user_role) {
        if (in_array($user_role, [2, 3, 4, 5])) {
            $general_chat_id = get_or_create_general_chat_id($conn, 1);
            $stmt_ensure = $conn->prepare("INSERT IGNORE INTO chat_participants (conversation_id, user_id) VALUES (?, ?)");
            $stmt_ensure->execute([$general_chat_id, $user_id]);
        }
        
        $sql = "
            SELECT
                c.id, c.type, c.name AS group_name, c.created_by,
                (SELECT GROUP_CONCAT(p_sub.user_id) FROM chat_participants p_sub WHERE p_sub.conversation_id = c.id) AS participant_ids,
                other_user.nombre_completo AS participant_name,
                other_user.avatar_url AS participant_avatar,
                lm.last_message,
                lm.last_message_at
            FROM
                chat_participants user_p
            JOIN
                chat_conversations c ON user_p.conversation_id = c.id
            LEFT JOIN
                chat_participants other_p ON c.id = other_p.conversation_id AND other_p.user_id != :user_id
            LEFT JOIN
                usuarios other_user ON other_p.user_id = other_user.id AND c.type = 'one_to_one'
            LEFT JOIN
                (
                    SELECT conversation_id, message_content AS last_message, sent_at AS last_message_at
                    FROM chat_messages
                    WHERE id IN (SELECT MAX(id) FROM chat_messages GROUP BY conversation_id)
                ) AS lm ON c.id = lm.conversation_id
            WHERE
                user_p.user_id = :user_id
            GROUP BY c.id
            ORDER BY lm.last_message_at DESC;
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        $db_conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $conversations = array_map(function($con) {
            $is_group = $con['type'] === 'group';
            $name = $is_group ? $con['group_name'] : ($con['participant_name'] ?: 'Usuario Eliminado');
            $participant_ids = !empty($con['participant_ids']) ? array_map('intval', explode(',', $con['participant_ids'])) : [];

            $avatar = $con['participant_avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($name);
            if ($is_group) {
                $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=random';
                if ($name === 'General') {
                    $avatar = 'https://cdn-icons-png.flaticon.com/512/3388/3388873.png';
                }
            }

            return [
                'id' => (int)$con['id'],
                'name' => $name,
                'avatar' => $avatar,
                'is_group' => $is_group,
                'last_message' => $con['last_message'] ?: '',
                'participant_ids' => $participant_ids,
                'created_by' => (int)($con['created_by'] ?? 0)
            ];
        }, $db_conversations);

        echo json_encode(['success' => true, 'data' => $conversations]);
    }

    function get_messages($conn, $user_id, $conversation_id) {
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
        $stmt_check->execute([$conversation_id, $user_id]);
        if ($stmt_check->fetchColumn() == 0) throw new Exception('Acceso denegado');

        $sql = "SELECT id, sender_id, message_content, sent_at, (sender_id = ?) AS is_sender FROM chat_messages WHERE conversation_id = ? ORDER BY sent_at ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id, $conversation_id]);
        $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = array_map(function($m) {
            return array_merge($m, ['timestamp_formatted' => date('H:i', strtotime($m['sent_at']))]);
        }, $msgs);

        echo json_encode(['success' => true, 'data' => $data]);
    }

    // MODIFICADA para aceptar nombre de usuario
    function send_message($conn, $user_id, $user_name, $conversation_id, $message) {
        if (trim($message) === '') throw new Exception('El mensaje no puede estar vacío');

        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
        $stmt_check->execute([$conversation_id, $user_id]);
        if ($stmt_check->fetchColumn() == 0) throw new Exception('No puedes enviar mensajes a esta conversación');

        $sql = "INSERT INTO chat_messages (conversation_id, sender_id, message_content) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$conversation_id, $user_id, $message]);
        
        $lastId = $conn->lastInsertId();
        $stmt2 = $conn->prepare("SELECT sent_at FROM chat_messages WHERE id = ?");
        $stmt2->execute([$lastId]);
        $sent_at = $stmt2->fetchColumn();

        // +++ NUEVA LÓGICA DE NOTIFICACIÓN +++
        $payload = [
            'type' => 'notification',
            'payload' => [
                'type' => 'new_chat_message',
                'sender' => $user_name
            ]
        ];
        notify_websocket($payload);

        echo json_encode(['success' => true, 'data' => ['id' => (int)$lastId, 'timestamp_formatted' => date('H:i', strtotime($sent_at))]]);
    }

    function search_users($conn, $current_user_id, $query, $exclude_ids_json = '[]') {
        if (trim($query) === '') {
            echo json_encode(['success' => true, 'data' => []]);
            return;
        }

        $exclude_ids = json_decode($exclude_ids_json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($exclude_ids)) $exclude_ids = [];
        $exclude_ids[] = $current_user_id;
        $exclude_ids = array_unique(array_map('intval', $exclude_ids));
        
        $placeholders = !empty($exclude_ids) ? implode(',', array_fill(0, count($exclude_ids), '?')) : '0';
        $search_term = '%' . mb_strtolower($query, 'UTF-8') . '%';

        $sql = "SELECT id, nombre_completo, expediente, COALESCE(avatar_url, '') as avatar_url FROM usuarios WHERE (LOWER(nombre_completo) LIKE ? OR LOWER(expediente) LIKE ?) AND id NOT IN ($placeholders) LIMIT 10";
        $params = array_merge([$search_term, $search_term], $exclude_ids);
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $users]);
    }

    function start_conversation($conn, $user1_id, $user2_id) {
        if ($user1_id == $user2_id) throw new Exception('No puedes iniciar una conversación contigo mismo');

        $sql_find = "SELECT cp1.conversation_id FROM chat_participants cp1 JOIN chat_participants cp2 ON cp1.conversation_id = cp2.conversation_id JOIN chat_conversations cc ON cc.id = cp1.conversation_id WHERE cp1.user_id = ? AND cp2.user_id = ? AND cc.type = 'one_to_one' LIMIT 1";
        $stmt_find = $conn->prepare($sql_find);
        $stmt_find->execute([$user1_id, $user2_id]);
        if ($existing = $stmt_find->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => true, 'conversation_id' => (int)$existing['conversation_id']]);
            return;
        }

        $conn->beginTransaction();
        $stmt_conv = $conn->prepare("INSERT INTO chat_conversations (created_by, type) VALUES (?, 'one_to_one')");
        $stmt_conv->execute([$user1_id]);
        $conversation_id = $conn->lastInsertId();
        $stmt_part = $conn->prepare("INSERT INTO chat_participants (conversation_id, user_id) VALUES (?, ?), (?, ?)");
        $stmt_part->execute([$conversation_id, $user1_id, $conversation_id, $user2_id]);
        $conn->commit();

        echo json_encode(['success' => true, 'conversation_id' => (int)$conversation_id]);
    }

    function create_group($conn, $creator_id, $creator_role, $group_name, $member_ids_json) {
        if (!in_array($creator_role, [4,5])) throw new Exception('No tienes permiso para crear grupos.');
        if (empty($group_name)) throw new Exception('El nombre del grupo es obligatorio.');

        $member_ids = json_decode($member_ids_json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($member_ids)) throw new Exception('Lista de miembros inválida.');

        $conn->beginTransaction();
        $stmt_conv = $conn->prepare("INSERT INTO chat_conversations (name, type, created_by) VALUES (?, 'group', ?)");
        $stmt_conv->execute([$group_name, $creator_id]);
        $conversation_id = $conn->lastInsertId();

        $all_members = array_unique(array_merge($member_ids, [$creator_id]));
        if (empty($all_members)) throw new Exception('El grupo debe tener miembros.');

        $placeholders = implode(', ', array_fill(0, count($all_members), '(?, ?)'));
        $params = [];
        foreach ($all_members as $user_id) {
            $params[] = $conversation_id;
            $params[] = $user_id;
        }
        
        $stmt_part = $conn->prepare("INSERT IGNORE INTO chat_participants (conversation_id, user_id) VALUES $placeholders");
        $stmt_part->execute($params);
        $conn->commit();

        echo json_encode(['success' => true, 'conversation_id' => (int)$conversation_id, 'message' => 'Grupo creado exitosamente']);
    }
    
    function add_members($conn, $actor_id, $actor_role, $conversation_id, $member_ids_json) {
        if (!in_array($actor_role, [4, 5])) throw new Exception('No tienes permiso para añadir miembros.');

        $member_ids = json_decode($member_ids_json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($member_ids) || empty($member_ids)) {
            throw new Exception('Lista de nuevos miembros inválida.');
        }

        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
        $stmt_check->execute([$conversation_id, $actor_id]);
        if ($stmt_check->fetchColumn() == 0) throw new Exception('No puedes modificar un grupo del que no eres parte.');

        $placeholders = implode(', ', array_fill(0, count($member_ids), '(?, ?)'));
        $sql_insert = "INSERT IGNORE INTO chat_participants (conversation_id, user_id) VALUES $placeholders";
        
        $params = [];
        foreach ($member_ids as $user_id) {
            $params[] = $conversation_id;
            $params[] = (int)$user_id;
        }

        $conn->beginTransaction();
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->execute($params);
        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Miembros añadidos correctamente.']);
    }

    function get_group_members($conn, $user_id, $conversation_id) {
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
        $stmt_check->execute([$conversation_id, $user_id]);
        if ($stmt_check->fetchColumn() == 0) {
            throw new Exception('Acceso denegado: no eres miembro de este grupo.');
        }

        $sql = "
            SELECT u.id, u.nombre_completo, u.expediente
            FROM usuarios u JOIN chat_participants p ON u.id = p.user_id
            WHERE p.conversation_id = ? ORDER BY u.nombre_completo ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$conversation_id]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $members]);
    }

    function delete_message($conn, $user_id, $message_id) {
        $stmt_check = $conn->prepare("SELECT sender_id FROM chat_messages WHERE id = ?");
        $stmt_check->execute([$message_id]);
        $message = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$message) throw new Exception('El mensaje no existe o ya fue eliminado.');
        if ($message['sender_id'] != $user_id) throw new Exception('No tienes permiso para eliminar este mensaje.');

        $sql_delete = "DELETE FROM chat_messages WHERE id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->execute([$message_id]);

        if ($stmt_delete->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Mensaje eliminado para todos.']);
        } else {
            throw new Exception('No se pudo eliminar el mensaje.');
        }
    }

    function leave_conversation($conn, $user_id, $conversation_id) {
        // Opcional: Impedir abandonar el chat General
        $stmt_check = $conn->prepare("SELECT name, type FROM chat_conversations WHERE id = ?");
        $stmt_check->execute([$conversation_id]);
        $conv = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($conv && $conv['name'] === 'General' && $conv['type'] === 'group') {
            throw new Exception('No puedes abandonar el chat general.');
        }

        $sql_delete = "DELETE FROM chat_participants WHERE conversation_id = ? AND user_id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->execute([$conversation_id, $user_id]);

        if ($stmt_delete->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Has abandonado la conversación.']);
        } else {
            throw new Exception('No se pudo abandonar la conversación o ya no eras miembro.');
        }
    }

    // ---------- Router ----------
    switch ($action) {
        case 'get_conversations':
            get_conversations($conn, $current_user_id, $current_user_role);
            break;
        case 'get_messages':
            if (!isset($_GET['conversation_id'])) throw new Exception("Conversation ID es requerido.");
            get_messages($conn, $current_user_id, (int)$_GET['conversation_id']);
            break;
        case 'send_message':
            if (!isset($_POST['conversation_id']) || !isset($_POST['message'])) throw new Exception("Conversation ID y mensaje son requeridos.");
            // MODIFICADO para pasar el nombre de usuario
            send_message($conn, $current_user_id, $current_user_name, (int)$_POST['conversation_id'], $_POST['message']);
            break;
        case 'search_users':
            $query = $_GET['query'] ?? '';
            $exclude = $_GET['exclude'] ?? '[]';
            search_users($conn, $current_user_id, $query, $exclude);
            break;
        case 'start_conversation':
            if (!isset($_POST['user_id'])) throw new Exception("User ID es requerido.");
            start_conversation($conn, $current_user_id, (int)$_POST['user_id']);
            break;
        case 'create_group':
            if (!isset($_POST['name']) || !isset($_POST['members'])) throw new Exception("Nombre y miembros son requeridos.");
            create_group($conn, $current_user_id, $current_user_role, $_POST['name'], $_POST['members']);
            break;
        case 'add_members':
            if (!isset($_POST['conversation_id']) || !isset($_POST['members'])) throw new Exception("ID de conversación y miembros son requeridos.");
            add_members($conn, $current_user_id, $current_user_role, (int)$_POST['conversation_id'], $_POST['members']);
            break;
        case 'get_group_members':
            if (!isset($_GET['conversation_id'])) throw new Exception("Conversation ID es requerido.");
            get_group_members($conn, $current_user_id, (int)$_GET['conversation_id']);
            break;
        case 'delete_message':
            if (!isset($_POST['message_id'])) throw new Exception("Message ID es requerido.");
            delete_message($conn, $current_user_id, (int)$_POST['message_id']);
            break;
        case 'leave_group': // Nueva acción
        case 'delete_chat': // Nueva acción
            if (!isset($_POST['conversation_id'])) throw new Exception("Conversation ID es requerido.");
            leave_conversation($conn, $current_user_id, (int)$_POST['conversation_id']);
            break;
        default:
            throw new Exception("Acción no válida o no especificada: " . htmlspecialchars($action));
    }

} catch (Throwable $e) {
    http_response_code(500);
    if(ob_get_length()) ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit();
}
?>