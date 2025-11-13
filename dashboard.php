<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['user_id'])) {
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
    <title>Panel de Administración</title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">

    <header class="dashboard-header">
        <h1>Panel de Administración</h1>
        <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
    </header>

    <main class="dashboard-container">
        <section class="welcome-section">
            <h2>Bienvenido, <span class="user-name"><?php echo htmlspecialchars($nombreUsuario); ?></span></h2>
            <p class="subtext">Has iniciado sesión correctamente en el sistema.</p>
        </section>

        <section class="user-info">
            <h3>Información del Usuario</h3>
            <div class="info-card">
                <p><strong>Nombre:</strong> <?php echo htmlspecialchars($nombreUsuario); ?></p>
                <p><strong>Expediente:</strong> <?php echo htmlspecialchars($expedienteUsuario); ?></p>
            </div>
        </section>

        <section class="actions">
            <h3>Menú Principal</h3>
            <div class="button-grid">
                <a href="gestionar_personal.php" class="action-btn blue">👤 Gestionar Personal</a>
                <a href="gestionar_documentos.php" class="action-btn green">📁 Gestor de Documentos</a>
                <a href="historial_recibimiento.php" class="action-btn purple">📊 Histórico y Estadísticas</a>
                <a href="gestionar_manuales.php" class="action-btn orange">📚 Gestionar Manuales</a>
                <a href="revisar_oficios.php" class="action-btn red">📥 Bandeja de Oficios Recibidos</a>
                <a href="#" class="action-btn blue">📅 Calendario de Eventos</a>
            </div>
        </section>
    </main>

    <footer class="dashboard-footer">
        <p>© <?php echo date('Y'); ?> Sistema Administrativo | Todos los derechos reservados</p>
    </footer>

</body>
</html>