<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

// 1. --- VALIDACIÓN DE SEGURIDAD ---
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Acceso no autorizado. Por favor, inicia sesión.'];
    header("Location: index.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard_enlace.php");
    exit();
}

// 2. --- PROCESAMIENTO DEL ARCHIVO ---
if (isset($_FILES['oficio']) && $_FILES['oficio']['error'] === UPLOAD_ERR_OK) {

    $upload_dir = 'oficios_uploads/';
    $original_name = basename($_FILES['oficio']['name']);
    $comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : null;
    $user_id = $_SESSION['user_id'];
    $user_nombre = $_SESSION['user_nombre']; // Necesitamos el nombre para la notificación

    $timestamp = time();
    $safe_filename = $timestamp . '_' . $user_id . '_' . preg_replace("/[^a-zA-Z0-9._-]", '_', $original_name);
    $target_path = $upload_dir . $safe_filename;

    if (move_uploaded_file($_FILES['oficio']['tmp_name'], $target_path)) {
        
        // 3. --- REGISTRO EN LA BASE DE DATOS ---
        try {
            $stmt = $conn->prepare(
                "INSERT INTO oficios_personalizados (user_id, nombre_archivo_original, nombre_archivo_guardado, ruta_archivo, comentario) VALUES (:user_id, :nombre_original, :nombre_guardado, :ruta, :comentario)"
            );
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':nombre_original', $original_name, PDO::PARAM_STR);
            $stmt->bindParam(':nombre_guardado', $safe_filename, PDO::PARAM_STR);
            $stmt->bindParam(':ruta', $target_path, PDO::PARAM_STR);
            $stmt->bindParam(':comentario', $comentario, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Oficio subido y registrado correctamente.'];

                // *** PREPARAR NOTIFICACIÓN PARA WEBSOCKET ***
                $_SESSION['notification_data'] = [
                    'type' => 'new_upload',
                    'user' => $user_nombre, // Quién subió el archivo
                    'oficio' => $original_name // Qué archivo subió
                ];

            } else {
                $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Error: No se pudo registrar el oficio en la base de datos.'];
            }
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Error de base de datos: ' . $e->getMessage()];
        }

    } else {
        $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Error: No se pudo guardar el archivo subido en el servidor.'];
    }
} else {
    $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Error: No se seleccionó ningún archivo o hubo un problema con la subida.'];
}

// 4. --- REDIRECCIÓN ---
// Redirigir siempre de vuelta a la página de carga para mostrar el mensaje y enviar la notificación
header("Location: cargar_oficio.php");
exit();

?>