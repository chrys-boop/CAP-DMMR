<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// --- LÓGICA DE SEGURIDAD ---
// El rol 2 corresponde a Cap-dmmr
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
    <title>Panel de Cap-dmmr</title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        /* --- ESTILOS PARA NOTIFICACIONES --- */

        /* Contenedor del ícono de la campana */
        .notification-bell {
            position: relative;
            font-size: 24px;
            margin-right: 25px;
            cursor: pointer;
        }

        /* Burbuja del contador */
        .notification-counter {
            position: absolute;
            top: -5px;
            right: -10px;
            background-color: red;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            font-weight: bold;
            display: none; /* Oculto por defecto */
        }

        /* Contenedor para los toasts que aparecerán */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }

        /* Estilo del Toast */
        .toast {
            background-color: #2a3e50; /* Un color oscuro y elegante */
            color: #fff;
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            opacity: 0;
            transition: opacity 0.3s, transform 0.3s;
            transform: translateX(100%); /* Empieza fuera de la pantalla */
        }

        /* Clase que se añade para mostrar el toast con animación */
        .toast.show {
            opacity: 1;
            transform: translateX(0);
        }

    </style>
</head>
<body class="dashboard-page">

    <input type="hidden" id="user-role" value="cap-dmmr">

    <header class="dashboard-header">
        <h1>Panel de Cap-dmmr</h1>
        <div style="display: flex; align-items: center;">
            <div id="notification-container" class="notification-bell">
                <span>🔔</span>
                <span id="notification-count" class="notification-counter">0</span>
            </div>
            <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
        </div>
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
                <a href="gestionar_cursos.php" class="action-btn teal">🎓 Gestionar Cursos</a>
                <a href="registrar_asistencia.php" class="action-btn indigo">➕ Registrar Asistencia</a>
                <a href="estadisticas.php" class="action-btn steel-blue">📈 Estadísticas de Capacitación</a>
            </div>
        </section>
    </main>

    <footer class="dashboard-footer">
        <p>© <?php echo date('Y'); ?> Sistema Administrativo | Todos los derechos reservados</p>
    </footer>

    <!-- Contenedor donde se inyectarán los toasts dinámicamente -->
    <div id="toast-container" class="toast-container"></div>

    <script src="notifications.js"></script>

</body>
</html>
