document.addEventListener("DOMContentLoaded", function() {
    console.log("[init] DOM cargado. Iniciando script de notificaciones v2 (robusto).");

    const websocketUrl = "ws://localhost:8081";
    const userRoleElement = document.getElementById('user-role');
    const userRole = userRoleElement ? userRoleElement.value : 'guest';
    let socket;

    // --- Elementos del DOM ---
    const bellContainer = document.getElementById('notification-container');
    const counterElement = document.getElementById('notification-count');
    const toastContainer = document.getElementById('toast-container');
    const chatButton = document.getElementById('chat-button');
    
    function connect() {
        console.log("[connect] Intentando conectar a " + websocketUrl);

        try {
            socket = new WebSocket(websocketUrl);
        } catch(e) {
            console.error("[connect] Error al crear el WebSocket:", e);
            console.log("Reintentando en 5 segundos...");
            setTimeout(connect, 5000); // Reintenta si la creación falla
            return;
        }

        socket.onopen = () => {
            console.log("[open] Conexión establecida. Registrando rol: " + userRole);
            const registrationMessage = {
                type: 'register',
                role: userRole
            };
            socket.send(JSON.stringify(registrationMessage));
        };

        socket.onmessage = (event) => {
            console.log(`[message] Datos recibidos: ${event.data}`);
            try {
                const message = JSON.parse(event.data);

                if (message.type === 'notification' && message.payload) {
                    const data = message.payload;
                    
                    // --- MANEJO DE NOTIFICACIÓN DE CHAT ---
                    if (data.type === 'new_chat_message') {
                        // Solo mostramos la alerta si NO estamos en la página de chat
                        if (window.location.pathname.indexOf('chat.php') === -1) {
                            console.log(">>> Recibida alerta de nuevo mensaje de chat de: ", data.sender);
                            
                            // Mostrar un 'toast'
                            if (toastContainer) {
                                const toast = document.createElement('div');
                                toast.className = 'toast';
                                toast.innerText = `Nuevo mensaje en el chat de: ${data.sender}`;
                                toastContainer.appendChild(toast);
                                setTimeout(() => { toast.classList.add('show'); }, 100);
                                setTimeout(() => {
                                    toast.classList.remove('show');
                                    toast.addEventListener('transitionend', () => toast.remove());
                                }, 5000);
                            }

                            // Animar el botón de chat
                            if (chatButton) {
                                chatButton.classList.add('new-message-alert');
                                const h4 = chatButton.querySelector('h4');
                                if (h4) {
                                    h4.textContent = 'Nuevo Mensaje';
                                }
                            }
                        }
                    }
                    // Aquí se podrían añadir otros tipos de notificaciones (else if)
                }
            } catch (e) {
                console.error("Error procesando mensaje del servidor: ", e);
            }
        };

        socket.onclose = (event) => {
            // Esta es la parte clave: siempre intentará reconectar.
            console.log(`[close] Conexión cerrada. Limpia: ${event.wasClean}, Código: ${event.code}, Razón: ${event.reason}`);
            console.log("Reconectando en 5 segundos...");
            setTimeout(connect, 5000);
        };

        socket.onerror = (error) => {
            // El evento de error es genérico. El 'onclose' que le sigue nos dará más detalles.
            console.error("[error] Error de WebSocket detectado. Ver el evento 'onclose' para más detalles.");
        };
    }

    // --- Listeners para la UI ---
    if (bellContainer) {
        bellContainer.addEventListener('click', function() {
            if (counterElement) {
                counterElement.innerText = '0';
                counterElement.style.display = 'none';
            }
        });
    }
    
    if (chatButton) {
        chatButton.addEventListener('click', function() {
            chatButton.classList.remove('new-message-alert');
            const h4 = chatButton.querySelector('h4');
            if (h4) {
                // Restaura el texto original del menú de Enlace
                h4.textContent = 'Acceder al Chat';
            }
        });
    }

    // --- Iniciar la conexión ---
    console.log("[init] Llamando a connect() por primera vez.");
    connect();
});
