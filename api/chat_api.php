<?php
// Mostrar errores en desarrollo
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Convertir errores a excepciones
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return false;
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {
    header('Content-Type: application/json; charset=utf-8');

    session_start();
    require_once __DIR__ . '/../db_connection.php'; // Ajusta si tu ruta es distinta

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
        exit();
    }

    $current_user_id = $_SESSION['user_id'];
    $current_user_role = $_SESSION['user_role'] ?? null;
    $action = $_REQUEST['action'] ?? null;

    // ---------- Helpers ----------
    function get_or_create_general_chat_id($conn) {
        $sql_find = "SELECT id FROM chat_conversations WHERE type = 'group' AND name = 'General' LIMIT 1";
        $stmt = $conn->query($sql_find);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row['id'];

        $sql_create = "INSERT INTO chat_conversations (name, type, created_by) VALUES ('General', 'group', ?)";
        $stmt_create = $conn->prepare($sql_create);
        $stmt_create->execute([1]); // Asumimos que el admin con ID 1 lo crea
        return $conn->lastInsertId();
    }

    // ---------- ACTIONS ----------
    function get_conversations($conn, $user_id, $user_role) {
        $conversations = [];

        // 1. OBTENER TODAS LAS CONVERSACIONES (GRUPOS E INDIVIDUALES) DEL USUARIO
        $sql = "
            SELECT
                c.id,
                c.type,
                c.name AS group_name,
                other_user.nombre_completo AS participant_name,
                other_user.avatar_url AS participant_avatar,
                (SELECT msg.message_content FROM chat_messages msg WHERE msg.conversation_id = c.id ORDER BY msg.sent_at DESC LIMIT 1) AS last_message,
                (SELECT msg.sent_at FROM chat_messages msg WHERE msg.conversation_id = c.id ORDER BY msg.sent_at DESC LIMIT 1) AS last_message_at
            FROM chat_conversations c
            JOIN chat_participants p ON c.id = p.conversation_id
            LEFT JOIN chat_participants other_p ON c.id = other_p.conversation_id AND other_p.user_id != ?
            LEFT JOIN usuarios other_user ON other_p.user_id = other_user.id AND c.type = 'one_to_one'
            WHERE p.user_id = ?
            GROUP BY c.id
            ORDER BY last_message_at DESC;
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id, $user_id]);
        $db_conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $conversations = array_map(function($con) {
            $is_group = $con['type'] === 'group';
            $name = $is_group ? $con['group_name'] : ($con['participant_name'] ?: 'Usuario Eliminado');
            
            if ($is_group) {
                $default_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=random';
                // Icono especial para el chat General
                if ($name === 'General') {
                    $avatar = 'https://cdn-icons-png.flaticon.com/512/3388/3388873.png';
                } else {
                    $avatar = $default_avatar;
                }
            } else {
                $avatar = $con['participant_avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($name);
            }

            return [
                'id' => (int)$con['id'],
                'participant_name' => $name,
                'participant_avatar' => $avatar,
                'type' => $con['type'],
                'last_message' => $con['last_message'] ?: ''
            ];
        }, $db_conversations);
        
        // 2. ASEGURAR QUE EL CHAT 'General' EXISTA SI EL ROL LO PERMITE
        if (in_array($user_role, [2,3,4,5])) {
            $general_chat_exists = false;
            foreach ($conversations as $conv) {
                if ($conv['participant_name'] === 'General') {
                    $general_chat_exists = true;
                    break;
                }
            }
            if (!$general_chat_exists) {
                $general_chat_id = get_or_create_general_chat_id($conn);
                // Podríamos volver a hacer fetch, pero es más simple añadirlo manualmente si no está
                array_unshift($conversations, [
                    'id' => (int)$general_chat_id,
                    'participant_name' => 'General',
                    'participant_avatar' => 'https://cdn-icons-png.flaticon.com/512/3388/3388873.png',
                    'type' => 'group',
                    'last_message' => 'Chat de todo el personal'
                ]);
            }
        }

        echo json_encode(['success' => true, 'data' => $conversations]);
    }

    function get_messages($conn, $user_id, $conversation_id) {
        // ... (sin cambios en esta función)
        $sql_check = "SELECT COUNT(*) FROM chat_participants WHERE conversation_id = ? AND user_id = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->execute([$conversation_id, $user_id]);
        if ($stmt_check->fetchColumn() == 0) {
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            return;
        }

        $sql = "SELECT id, sender_id, message_content, sent_at, (sender_id = ?) AS is_sender FROM chat_messages WHERE conversation_id = ? ORDER BY sent_at ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id, $conversation_id]);
        $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = array_map(function($m) {
            $ts_friendly = $m['sent_at'] ? date('H:i', strtotime($m['sent_at'])) : '';
            return array_merge($m, ['timestamp_formatted' => $ts_friendly]);
        }, $msgs);

        echo json_encode(['success' => true, 'data' => $data]);
    }

    function send_message($conn, $user_id, $conversation_id, $message) {
        // ... (sin cambios en esta función)
        if (trim($message) === '') {
            echo json_encode(['success' => false, 'message' => 'El mensaje no puede estar vacío']);
            return;
        }

        $sql_check = "SELECT COUNT(*) FROM chat_participants WHERE conversation_id = ? AND user_id = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->execute([$conversation_id, $user_id]);
        if ($stmt_check->fetchColumn() == 0) {
            echo json_encode(['success' => false, 'message' => 'No puedes enviar mensajes a esta conversación.']);
            return;
        }

        $sql = "INSERT INTO chat_messages (conversation_id, sender_id, message_content) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$conversation_id, $user_id, $message]);
        
        $lastId = $conn->lastInsertId();
        $stmt2 = $conn->prepare("SELECT sent_at FROM chat_messages WHERE id = ?");
        $stmt2->execute([$lastId]);
        $sent_at = $stmt2->fetchColumn();

        echo json_encode([
            'success' => true, 
            'message' => 'Mensaje enviado',
            'data' => [
                'id' => (int)$lastId,
                'timestamp_formatted' => date('H:i', strtotime($sent_at))
            ]
        ]);
    }

    function search_users($conn, $current_user_id, $query) {
        // ... (sin cambios en esta función)
        if (trim($query) === '') {
            echo json_encode(['success' => true, 'data' => []]);
            return;
        }

        $search_term = '%' . mb_strtolower($query, 'UTF-8') . '%';
        $sql = "
            SELECT id, nombre_completo, expediente, COALESCE(avatar_url, '') as avatar_url
            FROM usuarios
            WHERE (LOWER(nombre_completo) LIKE ? OR LOWER(expediente) LIKE ?)
              AND id != ?
            LIMIT 10
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$search_term, $search_term, $current_user_id]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $users]);
    }

    function start_conversation($conn, $user1_id, $user2_id) {
        // ... (sin cambios en esta función)
        if ($user1_id == $user2_id) {
            echo json_encode(['success' => false, 'message' => 'No puedes iniciar una conversación contigo mismo']);
            return;
        }

        $sql_find = "SELECT cp1.conversation_id FROM chat_participants cp1 JOIN chat_participants cp2 ON cp1.conversation_id = cp2.conversation_id JOIN chat_conversations cc ON cc.id = cp1.conversation_id WHERE cp1.user_id = ? AND cp2.user_id = ? AND cc.type = 'one_to_one' LIMIT 1";
        $stmt_find = $conn->prepare($sql_find);
        $stmt_find->execute([$user1_id, $user2_id]);
        if ($existing = $stmt_find->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => true, 'conversation_id' => (int)$existing['conversation_id']]);
            return;
        }

        $conn->beginTransaction();
        $sql_conv = "INSERT INTO chat_conversations (created_by, type) VALUES (?, 'one_to_one')";
        $stmt_conv = $conn->prepare($sql_conv);
        $stmt_conv->execute([$user1_id]);
        $conversation_id = $conn->lastInsertId();

        $sql_part = "INSERT INTO chat_participants (conversation_id, user_id) VALUES (?, ?), (?, ?)";
        $stmt_part = $conn->prepare($sql_part);
        $stmt_part->execute([$conversation_id, $user1_id, $conversation_id, $user2_id]);
        $conn->commit();

        echo json_encode(['success' => true, 'conversation_id' => (int)$conversation_id]);
    }

    function create_group($conn, $creator_id, $creator_role, $group_name, $member_ids_json) {
        if (!in_array($creator_role, [4,5])) {
            throw new Exception('No tienes permiso para crear grupos.');
        }
        if (empty($group_name)) throw new Exception('El nombre del grupo es obligatorio.');

        $member_ids = json_decode($member_ids_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('Lista de miembros inválida.');
        if (empty($member_ids)) throw new Exception('El grupo debe tener al menos un miembro.');

        $conn->beginTransaction();
        
        $stmt_conv = $conn->prepare("INSERT INTO chat_conversations (name, type, created_by) VALUES (?, 'group', ?)");
        $stmt_conv->execute([$group_name, $creator_id]);
        $conversation_id = $conn->lastInsertId();

        $all_members = array_unique(array_merge($member_ids, [$creator_id]));
        
        $placeholders = implode(', ', array_fill(0, count($all_members), '(?, ?)'));
        $params = [];
        foreach ($all_members as $user_id) {
            $params[] = $conversation_id;
            $params[] = $user_id;
        }
        
        $stmt_part = $conn->prepare("INSERT INTO chat_participants (conversation_id, user_id) VALUES $placeholders");
        $stmt_part->execute($params);

        $conn->commit();

        echo json_encode(['success' => true, 'conversation_id' => (int)$conversation_id, 'message' => 'Grupo creado exitosamente']);
    }

    // ---------- Router ----------
    switch ($action) {
        case 'get_conversations':
            get_conversations($conn, $current_user_id, $current_user_role);
            break;
        case 'get_messages':
            $conversation_id = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : null;
            if ($conversation_id === null) throw new Exception("Conversation ID es requerido.");
            get_messages($conn, $current_user_id, $conversation_id);
            break;
        case 'send_message':
            $conversation_id = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : null;
            $message = $_POST['message'] ?? null;
            if ($conversation_id === null || $message === null) throw new Exception("Conversation ID y mensaje son requeridos.");
            send_message($conn, $current_user_id, $conversation_id, $message);
            break;
        case 'search_users':
            $query = $_GET['query'] ?? '';
            search_users($conn, $current_user_id, $query);
            break;
        case 'start_conversation':
            $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
            if ($user_id === null) throw new Exception("User ID es requerido.");
            start_conversation($conn, $current_user_id, $user_id);
            break;
        case 'create_group':
            $name = $_POST['name'] ?? null;
            $members = $_POST['members'] ?? null;
            if (!$name || !$members) throw new Exception("Nombre y miembros son requeridos.");
            create_group($conn, $current_user_id, $current_user_role, $name, $members);
            break;
        default:
            throw new Exception("Acción no válida o no especificada.");
    }

} catch (Throwable $e) {
    http_response_code(500);
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor: ' . $e->getMessage()
    ]);
    exit();
}
?>