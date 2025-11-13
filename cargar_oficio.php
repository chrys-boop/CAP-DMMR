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

$nombreUsuario = $_SESSION['user_nombre'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cargar Oficio Personalizado</title>
    <link rel="stylesheet" href="estilos/estiloscar_oficio.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>Cargar Oficio Personalizado</h1>
        <a href="dashboard_enlace.php" class="logout-btn">Volver al Menú</a>
    </header>

    <main class="dashboard-container">
        <?php
        // Mostrar mensajes flash que puedan existir
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            // --- CORRECCIÓN DEFINITIVA USANDO PRINTF ---
            printf('<div class="flash-message %s">%s</div>', htmlspecialchars($message['type']), htmlspecialchars($message['text']));
            unset($_SESSION['flash_message']);
        }
        ?>

        <div class="requerimiento-card" style="max-width: 700px; margin: 2rem auto;">
             <form action="procesar_oficio.php" method="post" enctype="multipart/form-data">
                <h4 style="text-align: center; margin-bottom: 1rem;">Sube tu Oficio</h4>
                <p style="text-align: center; margin-bottom: 2rem;">Selecciona el documento de oficio que deseas cargar. Puedes añadir un comentario si lo consideras necesario.</p>
                
                <div class="form-group">
                    <label for="oficio" style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Archivo del Oficio</label>
                    <input type="file" name="oficio" id="oficio" required>
                </div>

                <div class="form-group">
                     <label for="comentario" style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Comentario (Opcional)</label>
                    <textarea name="comentario" id="comentario" placeholder="Ej: Oficio de solicitud de recursos para el área de..."></textarea>
                </div>
                
                <button type="submit" class="action-btn-small blue" style="width: 100%; margin-top: 1rem;">Subir Oficio</button>
            </form>
        </div>
    </main>

    <footer class="dashboard-footer">
        <p>© <?php echo date('Y'); ?> Sistema Administrativo</p>
    </footer>
</body>
</html>