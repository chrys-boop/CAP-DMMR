<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

// 1. Validar sesión de usuario
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Lógica para el botón de volver
$dashboard_link = 'index.html';
if (isset($_SESSION['user_role'])) {
    switch ($_SESSION['user_role']) {
        case 5: $dashboard_link = 'dashboard.php'; break;
        case 4: $dashboard_link = 'dashboard_cap-dmmr.php'; break;
        case 3: $dashboard_link = 'dashboard_enlace.php'; break;
        case 2: $dashboard_link = 'dashboard_instructor.php'; break;
        case 1: $dashboard_link = 'dashboard_trabajador.php'; break;
    }
}

// 2. Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validaciones básicas
    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "TODOS LOS CAMPOS SON OBLIGATORIOS.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "LAS CONTRASEÑAS NUEVAS NO COINCIDEN.";
    } else {
        try {
            // Obtener el hash de la contraseña actual del usuario
            $stmt = $conn->prepare("SELECT password FROM usuarios WHERE id = :id");
            $stmt->execute(['id' => $user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verificar la contraseña antigua
            if ($user && password_verify($old_password, $user['password'])) {
                // La contraseña antigua es correcta, hashear y actualizar la nueva
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                $update_stmt = $conn->prepare("UPDATE usuarios SET password = :password WHERE id = :id");
                $update_stmt->execute([
                    ':password' => $new_password_hash,
                    ':id' => $user_id
                ]);

                $success_message = "¡CONTRASEÑA ACTUALIZADA CON ÉXITO!";
            } else {
                // La contraseña antigua no es correcta
                $error_message = "LA CONTRASEÑA ANTIGUA ES INCORRECTA.";
            }
        } catch (PDOException $e) {
            $error_message = "ERROR DE BASE DE DATOS: " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAMBIAR CONTRASEÑA</title>
    <link rel="stylesheet" href="estilos/estilosgesdoc.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>CAMBIAR CONTRASEÑA</h1>
        <a href="<?php echo $dashboard_link; ?>" class="logout-btn">VOLVER AL PANEL</a>
    </header>

    <main class="dashboard-container">
        
        <?php if ($success_message): ?><div class="success-message"><?php echo $success_message; ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="error-message"><?php echo $error_message; ?></div><?php endif; ?>

        <section class="form-section">
            <form action="cambiar_contrasena.php" method="POST">
                <div class="form-group">
                    <label for="old_password">CONTRASEÑA ANTIGUA</label>
                    <input type="password" id="old_password" name="old_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">NUEVA CONTRASEÑA</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">CONFIRMAR NUEVA CONTRASEÑA</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="action-btn green">CAMBIAR CONTRASEÑA</button>
            </form>
        </section>
    </main>

    <footer class="dashboard-footer">
        <p>© <?php echo date('Y'); ?> SISTEMA ADMINISTRATIVO</p>
    </footer>

</body>
</html>