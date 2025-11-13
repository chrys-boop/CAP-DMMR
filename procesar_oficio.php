<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

// 1. --- VALIDACIÓN DE SEGURIDAD ---
// Solo usuarios con sesión iniciada pueden acceder
if (!isset($_SESSION['user_id'])) {
    // Si no hay sesión, se establece un mensaje de error y se detiene el script
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => 'Acceso no autorizado. Por favor, inicia sesión.'
    ];
    header("Location: index.html");
    exit();
}

// Verificar que la solicitud sea por método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Si no es POST, se redirige al panel principal
    header("Location: dashboard_enlace.php");
    exit();
}

// 2. --- PROCESAMIENTO DEL ARCHIVO ---

// Verificar si se subió un archivo y si no hubo errores
if (isset($_FILES['oficio']) && $_FILES['oficio']['error'] === UPLOAD_ERR_OK) {

    $upload_dir = 'oficios_uploads/'; // Directorio de destino
    $original_name = basename($_FILES['oficio']['name']);
    $comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : null;
    $user_id = $_SESSION['user_id'];

    // Crear un nombre de archivo único y seguro para evitar sobreescrituras
    // Formato: timestamp_userid_nombreoriginal.extension
    $timestamp = time();
    $safe_filename = $timestamp . '_' . $user_id . '_' . preg_replace("/[^a-zA-Z0-9._-]", '_', $original_name);
    $target_path = $upload_dir . $safe_filename;

    // Mover el archivo desde la ubicación temporal al directorio final
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
                // Si todo sale bien, se establece un mensaje de éxito
                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'text' => 'Oficio subido y registrado correctamente.'
                ];
            } else {
                // Error si la consulta falla
                $_SESSION['flash_message'] = [
                    'type' => 'error',
                    'text' => 'Error: No se pudo registrar el oficio en la base de datos.'
                ];
            }
        } catch (PDOException $e) {
            // Capturar errores de la base de datos
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'text' => 'Error de base de datos: ' . $e->getMessage()
            ];
        }

    } else {
        // Error si no se puede mover el archivo
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'text' => 'Error: No se pudo guardar el archivo subido en el servidor.'
        ];
    }
} else {
    // Error si no se subió el archivo o hubo un problema en la subida
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => 'Error: No se seleccionó ningún archivo o hubo un problema con la subida.'
    ];
}

// 4. --- REDIRECCIÓN ---
// Redirigir siempre de vuelta a la página de carga para mostrar el mensaje
header("Location: cargar_oficio.php");
exit();

?>