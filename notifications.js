// Definir el socket en un alcance más amplio.
let socket;

// 1. FUNCIÓN DE ENVÍO GLOBAL
window.sendNotification = function(payload) {
    const message = {
        type: 'notification',
        payload: payload
    };

    if (socket && socket.readyState === WebSocket.OPEN) {
        socket.send(JSON.stringify(message));
        console.log("[send] Mensaje de notificación enviado al servidor:", message);
    } else {
        console.log("[send] Conexión no lista. Reintentando envío en 500ms...");
        setTimeout(() => window.sendNotification(payload), 500);
    }
}

// 2. CÓDIGO QUE SE EJECUTA CUANDO EL DOM ESTÁ LISTO
document.addEventListener("DOMContentLoaded", function() {

    const websocketUrl = "ws://localhost:8081";
    const userRoleElement = document.getElementById('user-role');
    const userRole = userRoleElement ? userRoleElement.value : 'guest'; // Obtener el rol del usuario

    // --- Elementos del DOM ---
    const bellContainer = document.getElementById('notification-container');
    const counterElement = document.getElementById('notification-count');
    const toastContainer = document.getElementById('toast-container');

    // --- FUNCIÓN PARA ACTUALIZAR EL CONTADOR ---
    function updateCounter() {
        if (!counterElement) return;
        let currentCount = parseInt(counterElement.innerText, 10) || 0;
        currentCount++;
        counterElement.innerText = currentCount;
        counterElement.style.display = 'block';
    }

    // --- FUNCIÓN PARA MOSTRAR UN TOAST ---
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

    function connect() {
        socket = new WebSocket(websocketUrl);

        socket.onopen = () => {
            console.log("[open] Conexión establecida con el servidor WebSocket.");
            const registrationMessage = {
                type: 'register',
                role: userRole
            };
            socket.send(JSON.stringify(registrationMessage));
            console.log(`[register] Registrando rol: ${userRole}`);
        };

        socket.onmessage = function(event) {
            console.log(`[message] Datos recibidos del servidor: ${event.data}`);
            try {
                const message = JSON.parse(event.data);

                // Comprobar si el mensaje es del tipo 'notificación' y tiene un payload.
                if (message.type === 'notification' && message.payload) {
                    const data = message.payload; // ¡Este es el contenido real para el toast!

                    if (data.type === 'new_upload') {
                        showToast(`¡Nuevo oficio!\n${data.user} ha subido: ${data.oficio}`);
                        updateCounter();
                    } else if (data.type === 'new_manual') {
                        showToast(`¡Nuevo documento disponible!\nSe ha subido: ${data.manual_name}`);
                        updateCounter();
                    }
                } else {
                    console.warn("[warn] Mensaje recibido no tiene el formato de notificación esperado:", message);
                }
            } catch (error) {
                console.error("[error] No se pudo interpretar el mensaje del servidor:", error);
            }
        };

        socket.onclose = function(event) {
            if (!event.wasClean) {
                console.error('[close] La conexión se cayó. Intentando reconectar en 3 segundos...');
                setTimeout(connect, 3000);
            }
        };

        socket.onerror = error => console.error(`[error] Error de WebSocket: ${error.message}`);
    }

    if (bellContainer) {
        bellContainer.addEventListener('click', function() {
            if (counterElement) {
                counterElement.innerText = '0';
                counterElement.style.display = 'none';
            }
        });
    }

    connect();
});
