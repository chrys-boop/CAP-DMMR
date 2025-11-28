<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

$user_role = $_SESSION['user_role'] ?? null;
$user_id = $_SESSION['user_id'];

// Determinar el enlace del dashboard basado en el rol del usuario
$dashboard_link = 'index.html'; // Por defecto
if (isset($_SESSION['user_role'])) {
    switch ($_SESSION['user_role']) {
        case 5: // Admin
            $dashboard_link = 'dashboard.php';
            break;
        case 4: // CAP-DMMR
            $dashboard_link = 'dashboard_cap-dmmr.php';
            break;
        case 3: // Enlace
            $dashboard_link = 'dashboard_enlace.php';
            break;
        case 2: // Instructor
            $dashboard_link = 'dashboard_instructor.php';
            break;
        case 1: // Trabajador
            $dashboard_link = 'dashboard_trabajador.php';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHAT</title>
    <link rel="stylesheet" href="estilos/chat.css?v=1.3">
    <script>
        const USER_ROLE = <?php echo json_encode($user_role); ?>;
        const USER_ID = <?php echo json_encode($user_id); ?>;
    </script>
</head>
<body>
    <header class="page-header">
        <h1>PLATAFORMA DE CHAT</h1>
        <a href="<?php echo $dashboard_link; ?>" class="back-to-dashboard-btn">VOLVER AL PANEL</a>
    </header>

    <div class="chat-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>MENSAJES</h3>
                <div class="sidebar-actions">
                    <button id="new-chat-btn" title="NUEVO CHAT INDIVIDUAL">&#43;</button>
                    
                    <?php if (in_array($user_role, [4, 5])): ?>
                        <button id="create-group-btn" title="CREAR GRUPO">&#128101;</button>
                    <?php endif; ?>
                </div>
            </div>
            <div id="conversations-list" class="conversations-list">
                <!-- Las conversaciones se cargarán aquí dinámicamente -->
            </div>
        </div>

        <div class="chat-area">
            <div id="chat-welcome-screen" class="chat-welcome-screen">
                <h2>BIENVENIDO AL CHAT</h2>
                <p>SELECCIONA UNA CONVERSACIÓN PARA EMPEZAR A CHATEAR O INICIA UNA NUEVA.</p>
            </div>

            <div id="chat-active-conversation" class="chat-active-conversation" style="display: none;">
                <div class="chat-header">
                    <div class="chat-header-info">
                        <img src="" alt="Avatar" id="chat-with-avatar">
                        <span id="chat-with-user"></span>
                    </div>
                    <div id="chat-header-actions" class="chat-header-actions"></div>
                </div>
                <div id="messages-container" class="messages-container"></div>
                <footer class="chat-footer">
                    <form id="message-form" class="message-form">
                        <input type="text" id="message-input" placeholder="ESCRIBE UN MENSAJE..." autocomplete="off">
                        <button type="submit" title="ENVIAR">&#10148;</button>
                    </form>
                </footer>
            </div>
        </div>
    </div>

    <!-- ===== MODALES ===== -->

    <!-- Modal para iniciar nuevo chat INDIVIDUAL -->
    <div id="new-chat-modal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>INICIAR NUEVA CONVERSACIÓN</h2>
            <input type="text" id="modal-search-users" class="modal-input" placeholder="BUSCAR POR NOMBRE O EXPEDIENTE...">
            <div id="modal-users-list" class="users-list-results"></div>
        </div>
    </div>

    <!-- Modal para CREAR GRUPO -->
    <div id="create-group-modal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>CREAR NUEVO GRUPO</h2>
            <input type="text" id="group-name-input" class="modal-input" placeholder="NOMBRE DEL GRUPO..." required>
            <div class="group-members-section">
                <p><strong>MIEMBROS SELECCIONADOS:</strong></p>
                <div id="group-selected-members" class="selected-members-list"></div>
            </div>
            <input type="text" id="group-modal-search-users" class="modal-input" placeholder="BUSCAR USUARIOS PARA AÑADIR...">
            <div id="group-modal-users-list" class="users-list-results"></div>
            <button id="submit-create-group" class="modal-submit-btn">CREAR GRUPO</button>
        </div>
    </div>
    
    <!-- Modal para AÑADIR MIEMBROS A UN GRUPO EXISTENTE -->
    <div id="add-members-modal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>AÑADIR MIEMBROS AL GRUPO</h2>
            <p>ESTÁS AÑADIENDO MIEMBROS A: <strong id="add-members-group-name"></strong></p>
            <div class="group-members-section">
                <p><strong>NUEVOS MIEMBROS SELECCIONADOS:</strong></p>
                <div id="add-members-selected-list" class="selected-members-list"></div>
            </div>
            <input type="text" id="add-members-search-input" class="modal-input" placeholder="BUSCAR USUARIOS PARA AÑADIR...">
            <div id="add-members-search-results" class="users-list-results"></div>
            <button id="submit-add-members" class="modal-submit-btn">AÑADIR MIEMBROS</button>
        </div>
    </div>

    <!-- (NUEVO) Modal para VER MIEMBROS DE UN GRUPO -->
    <div id="view-members-modal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>MIEMBROS DEL GRUPO</h2>
            <p>MIEMBROS DE: <strong id="view-members-group-name"></strong></p>
            <div id="view-members-list" class="users-list-results"></div>
        </div>
    </div>


    <script src="js/chat.js?v=1.7"></script>
</body>
</html>