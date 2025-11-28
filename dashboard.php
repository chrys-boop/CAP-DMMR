<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// --- LÓGICA DE SEGURIDAD ---
// El rol 5 corresponde a Administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 5) {
    header("Location: index.html");
    exit();
}

$nombreUsuario = $_SESSION['user_nombre'];
$expedienteUsuario = $_SESSION['user_expediente'];
// --- MOSTRAR LA NUEVA INFORMACIÓN ---
$tallerUsuario = $_SESSION['user_taller'] ?? 'No especificado';
$areaInternaUsuario = $_SESSION['user_area_interna'] ?? 'No especificado';
$calidadLaboralUsuario = $_SESSION['user_calidad_laboral'] ?? 'No especificado';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PANEL DE ADMINISTRACIÓN</title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        /* --- ESTILOS PARA NOTIFICACIONES --- */
        .notification-bell {
            position: relative;
            font-size: 24px;
            margin-right: 25px;
            cursor: pointer;
        }
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
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
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
        .toast.show {
            opacity: 1;
            transform: translateX(0);
        }
        #chat-button.new-message-alert {
            background-color: #ffc107; /* Amarillo anaranjado */
            color: #333 !important; /* Texto oscuro para legibilidad */
            animation: pulse-animation 1.5s infinite;
        }
        @keyframes pulse-animation {
            0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); }
            70% { box-shadow: 0 0 0 12px rgba(255, 193, 7, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
        }
    </style>
</head>
<body class="dashboard-page">

    <input type="hidden" id="user-role" value="<?php echo htmlspecialchars($_SESSION['user_role']); ?>">
    <input type="hidden" id="user-id" value="<?php echo htmlspecialchars($_SESSION['user_id']); ?>">

    <header class="dashboard-header">
        <h1>PANEL DE ADMINISTRACIÓN</h1>
        <div style="display: flex; align-items: center;">
            <div id="notification-container" class="notification-bell">
                <span>🔔</span>
                <span id="notification-count" class="notification-counter">0</span>
            </div>
            <a href="cambiar_contrasena.php" class="logout-btn">CAMBIAR CONTRASEÑA</a>
            <a href="logout.php" class="logout-btn">CERRAR SESIÓN</a>
        </div>
    </header>

    <main class="dashboard-container">
        <section class="welcome-section">
            <h2>BIENVENIDO, <span class="user-name"><?php echo htmlspecialchars($nombreUsuario); ?></span></h2>
            <p class="subtext">HAS INICIADO SESIÓN CORRECTAMENTE EN EL SISTEMA.</p>
        </section>

        <section class="user-info">
            <h3>INFORMACIÓN DEL USUARIO</h3>
            <div class="info-card">
                <p><strong>NOMBRE:</strong> <?php echo htmlspecialchars($nombreUsuario); ?></p>
                <p><strong>EXPEDIENTE:</strong> <?php echo htmlspecialchars($expedienteUsuario); ?></p>
                <p><strong>TALLER:</strong> <?php echo htmlspecialchars($tallerUsuario); ?></p>
                <p><strong>ÁREA INTERNA:</strong> <?php echo htmlspecialchars($areaInternaUsuario); ?></p>
                <p><strong>CALIDAD LABORAL:</strong> <?php echo htmlspecialchars($calidadLaboralUsuario); ?></p>
            </div>
        </section>

        <section class="actions">
            <h3>MENÚ PRINCIPAL</h3>
            <div class="button-grid">
                <a href="gestionar_personal.php" class="action-btn blue">👤 GESTIONAR PERSONAL</a>
                <a href="plantilla_general.php" class="action-btn steel-blue">📜 PLANTILLA GENERAL</a>
                <a href="gestionar_documentos.php" class="action-btn green">📁 GESTOR DE DOCUMENTOS</a>
                <a href="historial_recibimiento.php" class="action-btn purple">📊 HISTÓRICO Y ESTADÍSTICAS</a>
                <a href="gestionar_manuales.php" class="action-btn orange">📚 GESTIONAR MANUALES</a>
                <a href="revisar_oficios.php" class="action-btn red">📥 BANDEJA DE OFICIOS RECIBIDOS</a>
                <a href="gestionar_cursos.php" class="action-btn teal">🎓 GESTIONAR CURSOS</a>
                <a href="registrar_asistencia.php" class="action-btn indigo">➕ REGISTRAR ASISTENCIA</a>
                <a href="estadisticas.php" class="action-btn steel-blue">📈 ESTADÍSTICAS DE CAPACITACIÓN</a>
                <a href="chat.php" id="chat-button" class="action-btn purple">💬 ACCEDER AL CHAT</a>
            </div>
        </section>
    </main>

    <footer class="dashboard-footer">
        <p>© <?php echo date('Y'); ?> SISTEMA ADMINISTRATIVO | TODOS LOS DERECHOS RESERVADOS</p>
    </footer>

    <div id="toast-container" class="toast-container"></div>

    <script src="notifications.js"></script>

</body>
</html>
