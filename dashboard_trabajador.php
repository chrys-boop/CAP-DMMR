<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

// --- NUEVA LÓGICA DE SEGURIDAD SIMPLE ---
// Si el usuario no ha iniciado sesión o su rol no es Trabajador (1), se le expulsa.
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
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
    <title>Panel de Trabajador</title>
    <link rel="stylesheet" href="estilos/estilosdashenlace.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">
    <header class="dashboard-header">
        <h1>Panel de Trabajador</h1>
        <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
    </header>
    <main class="dashboard-container">
        <section class="welcome-section">
            <h2>Bienvenido, <span class="user-name"><?php echo htmlspecialchars($nombreUsuario); ?></span></h2>
            <p class="subtext">Aquí puede ver los manuales y diagramas.</p>
        </section>
        
        <section class="actions">
             <h3>Menú Principal</h3>
            <div class="menu-grid">
                 <a href="ver_manuales_diagramas.php" class="menu-card orange">
                     <div class="menu-icon">📚</div>
                     <div class="menu-text"><h4>Manuales y Diagramas</h4><p>Consulta la documentación</p></div>
                </a>
            </div>
        </section>

    </main>
    <footer class="dashboard-footer">
        <p>© <?php echo date('Y'); ?> Sistema Administrativo</p>
    </footer>
</body>
</html>
