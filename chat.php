<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

// Si el usuario no está logueado, redirigirlo a la página de inicio de sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

$user_id = $_SESSION['user_id'];

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat</title>
    <link rel="stylesheet" href="estilos/chat.css">
</head>
<body>

    <div class="chat-container">
        <!-- Panel izquierdo: Lista de conversaciones y usuarios -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>Mensajes</h3>
                <button id="new-chat-btn" title="Nuevo Chat">+</button>
            </div>
            <div class="search-bar">
                <input type="text" id="search-users" placeholder="Buscar usuarios...">
            </div>
            <div id="conversations-list" class="conversations-list">
                <!-- Las conversaciones se cargarán aquí dinámicamente -->
            </div>
        </div>

        <!-- Panel derecho: Vista de la conversación activa -->
        <div class="chat-area">
            <div id="chat-welcome-screen" class="chat-welcome-screen">
                <h2>Bienvenido al Chat</h2>
                <p>Selecciona una conversación para empezar a chatear o inicia una nueva.</p>
            </div>

            <div id="chat-active-conversation" class="chat-active-conversation" style="display: none;">
                <div class="chat-header">
                    <h3 id="chat-with-user"></h3>
                    <!-- Aquí podrías agregar más opciones, como un menú -->
                </div>
                <div id="messages-container" class="messages-container">
                    <!-- Los mensajes se cargarán aquí -->
                </div>
                <div class="message-input-area">
                    <form id="message-form" class="message-form">
                        <input type="text" id="message-input" placeholder="Escribe un mensaje..." autocomplete="off">
                        <button type="submit">Enviar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para iniciar nuevo chat -->
    <div id="new-chat-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Iniciar Nueva Conversación</h2>
            <input type="text" id="modal-search-users" placeholder="Buscar por nombre o expediente...">
            <div id="modal-users-list"></div>
        </div>
    </div>

    <script src="js/chat.js"></script>
</body>
</html>
