document.addEventListener("DOMContentLoaded", function() {
    console.log("[init] DOM cargado. Iniciando script de notificaciones v3 (ID de Usuario).");

    // Obtener el ID de usuario del nuevo campo oculto
    const userIdElement = document.getElementById('user-id');
    const userId = userIdElement ? userIdElement.value : null;

    if (!userId) {
        console.error("[init] No se pudo encontrar el user-id. El sistema de notificaciones no se iniciará.");
        return; // Detiene la ejecución si no hay ID de usuario
    }

    const websocketUrl = "ws://localhost:8081";
    let socket;

    // --- Elementos del DOM ---
    const toastContainer = document.getElementById('toast-container');
    const chatButton = document.getElementById('chat-button');
    
    function connect() {
        console.log("[connect] Intentando conectar a " + websocketUrl + " para el usuario " + userId);

        try {
            socket = new WebSocket(websocketUrl);
        } catch(e) {
            console.error("[connect] Error al crear el WebSocket:", e);
            console.log("Reintentando en 5 segundos...");
            setTimeout(connect, 5000);
            return;
        }

        socket.onopen = () => {
            console.log("[open] Conexión establecida. Registrando ID de usuario: " + userId);
            // *** CAMBIO CLAVE: Registrarse con el ID de usuario ***
            const registrationMessage = {
                type: 'register',
                userId: userId
            };
            socket.send(JSON.stringify(registrationMessage));
        };

        socket.onmessage = (event) => {
            console.log(`[message] Datos recibidos: ${event.data}`);
            try {
                const message = JSON.parse(event.data);

                if (message.type === 'notification' && message.payload) {
                    const data = message.payload;
                    
                    if (data.type === 'new_chat_message') {
                        // Solo reacciona si la notificación es para este usuario y no está en la página de chat
                        if (window.location.pathname.indexOf('chat.php') === -1) {
                            console.log(">>> Recibida alerta de nuevo mensaje de chat de: ", data.sender);
                            showToast(`Nuevo mensaje en el chat de: ${data.sender}`);
                            animateChatButton();
                        }
                    }
                }
            } catch (e) {
                console.error("Error procesando mensaje del servidor: ", e);
            }
        };

        socket.onclose = (event) => {
            console.log(`[close] Conexión cerrada. Limpia: ${event.wasClean}, Código: ${event.code}`);
            console.log("Reconectando en 5 segundos...");
            setTimeout(connect, 5000);
        };

        socket.onerror = (error) => {
            console.error("[error] Error de WebSocket detectado.");
            socket.close(); // Forzar el cierre para activar la reconexión
        };
    }

    // --- Funciones de UI ---
    function showToast(message) {
        if (!toastContainer) return;
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.innerText = message;
        toastContainer.appendChild(toast);
        setTimeout(() => { toast.classList.add('show'); }, 100);
        setTimeout(() => {
            toast.classList.remove('show');
            toast.addEventListener('transitionend', () => toast.remove());
        }, 5000);
    }

    function animateChatButton() {
        if (!chatButton) return;
        chatButton.classList.add('new-message-alert');
        const h4 = chatButton.querySelector('h4');
        if (h4) {
            h4.textContent = 'Nuevo Mensaje';
        }
    }

    if (chatButton) {
        chatButton.addEventListener('click', function() {
            chatButton.classList.remove('new-message-alert');
            const h4 = chatButton.querySelector('h4');
            if (h4) {
                h4.textContent = 'Acceder al Chat';
            }
        });
    }

    // --- Iniciar la conexión ---
    connect();
});