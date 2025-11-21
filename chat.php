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
    <title>Chat</title>
    <link rel="stylesheet" href="estilos/chat.css?v=1.3">
    <script>
        const USER_ROLE = <?php echo json_encode($user_role); ?>;
        const USER_ID = <?php echo json_encode($user_id); ?>;
    </script>
</head>
<body>
    <header class="page-header">
        <h1>Plataforma de Chat</h1>
        <a href="<?php echo $dashboard_link; ?>" class="back-to-dashboard-btn">Volver al Panel</a>
    </header>

    <div class="chat-container">
        <!-- ... (el resto del contenido del chat no cambia) ... -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>Mensajes</h3>
                <div class="sidebar-actions">
                    <button id="new-chat-btn" title="Nuevo Chat Individual">&#43;</button>
                    
                    <?php if (in_array($user_role, [4, 5])): ?>
                        <button id="create-group-btn" title="Crear Grupo">&#128101;</button>
                    <?php endif; ?>
                </div>
            </div>
            <div id="conversations-list" class="conversations-list">
                <!-- Las conversaciones se cargarán aquí dinámicamente -->
            </div>
        </div>

        <div class="chat-area">
            <div id="chat-welcome-screen" class="chat-welcome-screen">
                <h2>Bienvenido al Chat</h2>
                <p>Selecciona una conversación para empezar a chatear o inicia una nueva.</p>
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
                        <input type="text" id="message-input" placeholder="Escribe un mensaje..." autocomplete="off">
                        <button type="submit" title="Enviar">&#10148;</button>
                    </form>
                </footer>
            </div>
        </div>
    </div>

    <!-- ... (Modales) ... -->
     <!-- ===== MODALES ===== -->

    <!-- Modal para iniciar nuevo chat INDIVIDUAL -->
    <div id="new-chat-modal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Iniciar Nueva Conversación</h2>
            <input type="text" id="modal-search-users" class="modal-input" placeholder="Buscar por nombre o expediente...">
            <div id="modal-users-list" class="users-list-results"></div>
        </div>
    </div>

    <!-- Modal para CREAR GRUPO -->
    <div id="create-group-modal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Crear Nuevo Grupo</h2>
            <input type="text" id="group-name-input" class="modal-input" placeholder="Nombre del grupo..." required>
            <div class="group-members-section">
                <p><strong>Miembros seleccionados:</strong></p>
                <div id="group-selected-members" class="selected-members-list"></div>
            </div>
            <input type="text" id="group-modal-search-users" class="modal-input" placeholder="Buscar usuarios para añadir...">
            <div id="group-modal-users-list" class="users-list-results"></div>
            <button id="submit-create-group" class="modal-submit-btn">Crear Grupo</button>
        </div>
    </div>
    
    <!-- Modal para AÑADIR MIEMBROS A UN GRUPO EXISTENTE -->
    <div id="add-members-modal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Añadir Miembros al Grupo</h2>
            <p>Estás añadiendo miembros a: <strong id="add-members-group-name"></strong></p>
            <div class="group-members-section">
                <p><strong>Nuevos miembros seleccionados:</strong></p>
                <div id="add-members-selected-list" class="selected-members-list"></div>
            </div>
            <input type="text" id="add-members-search-input" class="modal-input" placeholder="Buscar usuarios para añadir...">
            <div id="add-members-search-results" class="users-list-results"></div>
            <button id="submit-add-members" class="modal-submit-btn">Añadir Miembros</button>
        </div>
    </div>

    <!-- (NUEVO) Modal para VER MIEMBROS DE UN GRUPO -->
    <div id="view-members-modal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Miembros del Grupo</h2>
            <p>Miembros de: <strong id="view-members-group-name"></strong></p>
            <div id="view-members-list" class="users-list-results"></div>
        </div>
    </div>


    <script src="js/chat.js?v=1.7"></script>
</body>
</html>