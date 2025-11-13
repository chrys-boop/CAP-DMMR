<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';
require_once __DIR__ . '/vendor/autoload.php'; // Autoloader de Composer

use WebSocket\Client; // Usar el cliente de WebSocket

// 1. --- VALIDACIÓN DE SEGURIDAD ---
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: cargar_oficio.php"); // Redirigir a la página de carga si no es POST
    exit();
}

// 2. --- PROCESAMIENTO DEL ARCHIVO ---
if (isset($_FILES['oficio']) && $_FILES['oficio']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'oficios_uploads/';
    $original_name = basename($_FILES['oficio']['name']);
    $comentario = trim($_POST['comentario'] ?? '');
    $user_id = $_SESSION['user_id'];
    $user_nombre = $_SESSION['user_nombre'];

    // Crear un nombre de archivo seguro y único
    $safe_filename = time() . '_' . $user_id . '_' . preg_replace("/[^a-zA-Z0-9._-]", '_', $original_name);
    $target_path = $upload_dir . $safe_filename;

    if (move_uploaded_file($_FILES['oficio']['tmp_name'], $target_path)) {
        try {
            // 3. --- REGISTRO EN LA BASE DE DATOS ---
            $stmt = $conn->prepare(
                "INSERT INTO oficios_personalizados (user_id, nombre_archivo_original, nombre_archivo_guardado, ruta_archivo, comentario) VALUES (:uid, :n_orig, :n_guard, :ruta, :com)"
            );
            $stmt->execute([
                ':uid' => $user_id,
                ':n_orig' => $original_name,
                ':n_guard' => $safe_filename,
                ':ruta' => $target_path,
                ':com' => $comentario
            ]);

            $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Oficio subido y registrado correctamente.'];

            // 4. --- NOTIFICACIÓN DIRECTA POR WEBSOCKET (MÉTODO UNIFICADO) ---
            try {
                $ws_client = new Client("ws://localhost:8081");
                
                $payload_content = [
                    'type'   => 'new_upload', // El evento que el JS escucha
                    'user'   => $user_nombre,      // Quién subió el archivo
                    'oficio' => $original_name     // Qué archivo subió
                ];

                // Envolvemos el payload en el formato de mensaje que espera el servidor
                $message_to_send = json_encode([
                    'type'    => 'notification',
                    'payload' => $payload_content
                ]);

                $ws_client->send($message_to_send);

            } catch (\Exception $e) {
                error_log("Error al enviar notificación de oficio por WebSocket: " . $e->getMessage());
            }
            // --- FIN DE LA NOTIFICACIÓN ---

        } catch (PDOException $e) {
            $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Error de base de datos: ' . $e->getMessage()];
        }
    } else {
        $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Error: No se pudo guardar el archivo subido.'];
    }
} else {
    $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Error: No se seleccionó ningún archivo o hubo un problema.'];
}

// 5. --- REDIRECCIÓN ---
header("Location: cargar_oficio.php");
exit();
?>

