<?php
// login.php

session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$expediente = $input['expediente'] ?? null;
$password = $input['password'] ?? null;

if (!$expediente || !$password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Expediente y contraseña son requeridos.']);
    exit();
}

try {
    // El SELECT * ya incluye la columna 'area' si existe en la tabla
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE expediente = :expediente");
    $stmt->bindParam(':expediente', $expediente);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $password === $user['password']) {
        // --- INICIO DE LA MODIFICACIÓN ---
        // Guardar todos los datos del usuario en la sesión, incluyendo el área
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_expediente'] = $user['expediente'];
        $_SESSION['user_nombre'] = $user['nombre_completo'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_area'] = $user['area']; // Guardamos el área del usuario
        // --- FIN DE LA MODIFICACIÓN ---

        // Definir la URL de redirección basada en el rol
        $redirect_url = '';
        switch ($user['role']) {
            case 5: // Administrador
                $redirect_url = 'dashboard.php';
                break;
            case 4: 
                $redirect_url = 'dashboard_cap-dmmr.php';
                break;
            case 3: // Enlace
                $redirect_url = 'dashboard_enlace.php';
                break;
            case 2:
                $redirect_url = 'dashboard_instructor.php';
                break;
            default:
                $redirect_url = 'dashboard_trabajador.php';
                break;
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Inicio de sesión exitoso.',
            'redirect' => $redirect_url
        ]);

    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Número de expediente o contraseña incorrectos.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()]); 
}

?>