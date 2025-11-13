// Se define el socket en un alcance más amplio para que ambas partes del script lo vean.
let socket;

// 1. LA FUNCIÓN SE CREA INMEDIATAMENTE AL CARGAR EL SCRIPT.
// Ahora está disponible globalmente desde el principio.
window.sendNotification = function(message) {
    // La función es "paciente": si el socket no está listo, se reintenta a sí misma.
    if (socket && socket.readyState === WebSocket.OPEN) {
        socket.send(JSON.stringify(message));
        console.log("[send] Mensaje de notificación enviado al servidor:", message);
    } else {
        console.log("[send] Conexión no lista. Reintentando envío en 500ms...");
        setTimeout(() => window.sendNotification(message), 500);
    }
}

// 2. EL RESTO DEL CÓDIGO ESPERA A QUE EL DOM ESTÉ LISTO, COMO DEBE SER.
document.addEventListener("DOMContentLoaded", function() {

    const websocketUrl = "ws://localhost:8081";

    // --- Elementos del DOM ---
    const bellContainer = document.getElementById('notification-container');
    const counterElement = document.getElementById('notification-count');
    const toastContainer = document.getElementById('toast-container');

    // --- FUNCIÓN REUTILIZABLE Y ROBUSTA PARA ACTUALIZAR EL CONTADOR ---
    function updateCounter() {
        if (!counterElement) return;
        let currentCount = parseInt(counterElement.innerText, 10);
        if (isNaN(currentCount)) {
            currentCount = 0;
        }
        currentCount++;
        counterElement.innerText = currentCount;
        if (counterElement.style.display !== 'block') {
            counterElement.style.display = 'block';
        }
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
        // La variable 'socket' que se usa aquí es la que declaramos arriba.
        socket = new WebSocket(websocketUrl);

        socket.onopen = () => console.log("[open] Conexión establecida con el servidor WebSocket.");

        socket.onmessage = function(event) {
            console.log(`[message] Datos recibidos del servidor: ${event.data}`);
            try {
                const data = JSON.parse(event.data);
                if (data.type === 'new_upload') {
                    showToast(`¡Nuevo oficio!\n${data.user} ha subido: ${data.oficio}`);
                    updateCounter();
                } else if (data.type === 'new_manual') {
                    showToast(`¡Nuevo documento disponible!\nSe ha subido: ${data.manual_name}`);
                    updateCounter();
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

    // Iniciar la conexión ahora que el DOM está listo.
    connect();
});
