<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

// --- LÓGICA DE SEGURIDAD ---\n// Si el usuario no ha iniciado sesión o su rol no es CAP-DMMR (4), se le expulsa.
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 4) {
    header("Location: index.html");
    exit();
}

$nombreUsuario = $_SESSION['user_nombre'];
$expedienteUsuario = $_SESSION['user_expediente'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de CAP-DMMR</title>
    <!-- Asegúrate de que la ruta al CSS sea la correcta -->
    <link rel="stylesheet" href="style.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>Panel de Control CAP-DMMR</h1>
        <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
    </header>

    <main class="dashboard-container">
        <section class="welcome-section">
            <h2>Bienvenido, <span class="user-name"><?php echo htmlspecialchars($nombreUsuario); ?></span></h2>
            <p class="subtext">Panel de visualización y gestión para CAP-DMMR.</p>
        </section>

        <section class="user-info">
            <h3>Información del Usuario</h3>
            <div class="info-card">
                <p><strong>Nombre:</strong> <?php echo htmlspecialchars($nombreUsuario); ?></p>
                <p><strong>Expediente:</strong> <?php echo htmlspecialchars($expedienteUsuario); ?></p>
            </div>
        </section>

        <!-- Aquí puedes añadir las acciones específicas para este rol -->
        <section class="actions">
            <h3>Menú Principal</h3>
            <div class="button-grid">
                <a href="#" class="action-btn blue">👤 Acción 1</a>
                <a href="#" class="action-btn green">📁 Acción 2</a>
            </div>
        </section>
    </main>

    <footer class="dashboard-footer">
        <p>© <?php echo date('Y'); ?> Sistema Administrativo | Todos los derechos reservados</p>
    </footer>

    <!-- Script de notificaciones en tiempo real -->
    <script src="assets/js/notifications.js"></script>
</body>
</html>
