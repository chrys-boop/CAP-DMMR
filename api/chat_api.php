<?php
// Configuración inicial para mostrar errores (solo para depuración)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Establecer un manejador de errores personalizado para convertir todos los errores en excepciones
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// --- COMIENZO DEL SCRIPT PRINCIPAL ---
try {
    header('Content-Type: application/json');

    session_start();
    require_once '../db_connection.php';

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
        exit();
    }

    $current_user_id = $_SESSION['user_id'];
    $action = $_REQUEST['action'] ?? null;

    function get_conversations($conn, $user_id) {
        $sql = "
            SELECT 
                c.id, 
                u.nombre_completo as participant_name,
                (SELECT message_content FROM chat_messages cm WHERE cm.conversation_id = c.id ORDER BY cm.sent_at DESC LIMIT 1) as last_message
            FROM chat_conversations c
            JOIN chat_participants p ON c.id = p.conversation_id
            JOIN usuarios u ON p.user_id = u.id
            WHERE p.user_id != ? AND c.id IN (SELECT conversation_id FROM chat_participants WHERE user_id = ?)
            GROUP BY c.id
            ORDER BY (SELECT sent_at FROM chat_messages cm WHERE cm.conversation_id = c.id ORDER BY cm.sent_at DESC LIMIT 1) DESC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id, $user_id]);
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $conversations]);
    }
    
    function get_messages($conn, $user_id, $conversation_id) {
        $sql_check = "SELECT COUNT(*) FROM chat_participants WHERE conversation_id = ? AND user_id = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->execute([$conversation_id, $user_id]);
        if ($stmt_check->fetchColumn() == 0) {
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            return;
        }
        $sql = "SELECT id, sender_id, message_content, sent_at, (sender_id = ?) as is_sender FROM chat_messages WHERE conversation_id = ? ORDER BY sent_at ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id, $conversation_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $messages]);
    }
    
    function send_message($conn, $user_id, $conversation_id, $message) {
        if (empty($message)) {
            echo json_encode(['success' => false, 'message' => 'El mensaje no puede estar vacío']);
            return;
        }
        $sql_check = "SELECT COUNT(*) FROM chat_participants WHERE conversation_id = ? AND user_id = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->execute([$conversation_id, $user_id]);
        if ($stmt_check->fetchColumn() == 0) {
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            return;
        }
        $sql = "INSERT INTO chat_messages (conversation_id, sender_id, message_content) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt->execute([$conversation_id, $user_id, $message])) {
            echo json_encode(['success' => true, 'message' => 'Mensaje enviado']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al enviar el mensaje']);
        }
    }
    
    function search_users($conn, $current_user_id, $query) {
        if (empty($query)) {
            echo json_encode(['success' => true, 'data' => []]);
            return;
        }
        $search_term = '%' . strtolower($query) . '%';
        // CORRECCIÓN: Hacer la búsqueda de `expediente` insensible a mayúsculas/minúsculas.
        $sql = "
            SELECT id, nombre_completo, expediente 
            FROM usuarios 
            WHERE 
                (LOWER(nombre_completo) LIKE ? OR LOWER(expediente) LIKE ?) 
                AND id != ? 
                AND role IN (2, 3, 4, 5) 
            LIMIT 10
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$search_term, $search_term, $current_user_id]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $users]);
    }
    
    function start_conversation($conn, $user1_id, $user2_id) {
        if ($user1_id == $user2_id) {
            echo json_encode(['success' => false, 'message' => 'No puedes iniciar una conversación contigo mismo']);
            return;
        }
        $sql_find = "SELECT cp1.conversation_id FROM chat_participants cp1 JOIN chat_participants cp2 ON cp1.conversation_id = cp2.conversation_id WHERE cp1.user_id = ? AND cp2.user_id = ? AND (SELECT type FROM chat_conversations WHERE id = cp1.conversation_id) = 'one_to_one'";
        $stmt_find = $conn->prepare($sql_find);
        $stmt_find->execute([$user1_id, $user2_id]);
        $existing_conversation = $stmt_find->fetch(PDO::FETCH_ASSOC);
        if ($existing_conversation) {
            echo json_encode(['success' => true, 'conversation_id' => $existing_conversation['conversation_id'], 'message' => 'La conversación ya existe']);
            return;
        }
        $conn->beginTransaction();
        try {
            $sql_conv = "INSERT INTO chat_conversations (created_by, type) VALUES (?, 'one_to_one')";
            $stmt_conv = $conn->prepare($sql_conv);
            $stmt_conv->execute([$user1_id]);
            $conversation_id = $conn->lastInsertId();
            $sql_part = "INSERT INTO chat_participants (conversation_id, user_id) VALUES (?, ?), (?, ?)";
            $stmt_part = $conn->prepare($sql_part);
            $stmt_part->execute([$conversation_id, $user1_id, $conversation_id, $user2_id]);
            $conn->commit();
            echo json_encode(['success' => true, 'conversation_id' => $conversation_id, 'message' => 'Conversación iniciada exitosamente']);
        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    switch ($action) {
        case 'get_conversations':
            get_conversations($conn, $current_user_id);
            break;
        case 'get_messages':
            $conversation_id = $_GET['conversation_id'] ?? null;
            if ($conversation_id === null) throw new Exception("Conversation ID es requerido.");
            get_messages($conn, $current_user_id, $conversation_id);
            break;
        case 'send_message':
            $conversation_id = $_POST['conversation_id'] ?? null;
            $message = $_POST['message'] ?? null;
            if ($conversation_id === null || $message === null) throw new Exception("Conversation ID y mensaje son requeridos.");
            send_message($conn, $current_user_id, $conversation_id, $message);
            break;
        case 'search_users':
            $query = $_GET['query'] ?? '';
            search_users($conn, $current_user_id, $query);
            break;
        case 'start_conversation':
            $user_id = $_POST['user_id'] ?? null;
            if ($user_id === null) throw new Exception("User ID es requerido.");
            start_conversation($conn, $current_user_id, $user_id);
            break;
        default:
            throw new Exception("Acción no válida o no especificada.");
    }

} catch (Throwable $e) {
    http_response_code(500);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit();
}
