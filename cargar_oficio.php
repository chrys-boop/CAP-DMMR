<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

// 1. --- LÓGICA DE SEGURIDAD ---
// Debe haber un usuario logueado, y su rol debe ser 3 (Enlace)
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 3) {
    header("Location: index.html");
    exit();
}

$nombreUsuario = $_SESSION['user_nombre'];

// 2. --- MANEJO DE MENSAJES FLASH ---
$message = null;
$message_type = '';
if (isset($_SESSION['flash_message'])) {
    $flash = $_SESSION['flash_message'];
    $message = $flash['text'];
    $message_type = $flash['type'];
    unset($_SESSION['flash_message']);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CARGA DE OFICIO PERSONALIZADO</title>
    <link rel="stylesheet" href="estilos/estiloscar_oficio.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>CARGA DE OFICIO PERSONALIZADO</h1>
        <a href="dashboard_enlace.php" class="logout-btn">VOLVER AL PANEL</a>
    </header>

    <main class="dashboard-container">
        <section class="welcome-section">
            <h2>HOLA, <span class="user-name"><?php echo htmlspecialchars($nombreUsuario); ?></span></h2>
            <p class="subtext">AQUÍ PUEDES SUBIR TUS DOCUMENTOS PERSONALIZADOS (CARTAS DESCRIPTIVAS, ETC.) PARA QUE SEAN REVISADOS.</p>
        </section>
        
        <section class="upload-section">
            <h3>SUBIR NUEVO DOCUMENTO</h3>
            
            <?php if ($message): ?>
                <div class="flash-message <?php echo htmlspecialchars($message_type); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form action="procesar_oficio.php" method="post" enctype="multipart/form-data" class="upload-form">
                <div class="form-group">
                    <label for="documento">SELECCIONA EL ARCHIVO A SUBIR:</label>
                    <input type="file" name="documento" id="documento" required>
                </div>
                <div class="form-group">
                    <label for="comentario">COMENTARIO (OPCIONAL):</label>
                    <textarea name="comentario" id="comentario" rows="4" placeholder="AÑADE UN COMENTARIO AQUÍ (OPCIONAL)..."></textarea>
                </div>
                <div class="form-group">
                    <button type="submit" class="action-btn green">ENVIAR DOCUMENTO</button>
                </div>
            </form>
        </section>
    </main>

    <footer class="dashboard-footer">
        <p>© <?php echo date('Y'); ?> SISTEMA ADMINISTRATIVO</p>
    </footer>

</body>
</html>
