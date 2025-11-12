document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('login-form');
    const messageDiv = document.getElementById('message');

    if (loginForm) {
        loginForm.addEventListener('submit', function(event) {
            event.preventDefault(); // Evita el envío tradicional del formulario

            const expediente = document.getElementById('expediente').value;
            const password = document.getElementById('password').value;

            // Pequeña validación en el cliente
            if (!expediente || !password) {
                if (messageDiv) {
                    messageDiv.textContent = 'Por favor, complete todos los campos.';
                    messageDiv.className = 'message error';
                }
                return;
            }

            // Envío de datos con Fetch API
            fetch('login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ expediente: expediente, password: password })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    if (messageDiv) {
                        messageDiv.textContent = result.message;
                        messageDiv.className = 'message success';
                    }
                    // Redirigir a la URL proporcionada por el servidor
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 1000); // Espera 1 segundo para que el usuario vea el mensaje

                } else {
                    if (messageDiv) {
                        messageDiv.textContent = result.message || 'Ocurrió un error.';
                        messageDiv.className = 'message error';
                    }
                }
            })
            .catch(error => {
                console.error('Error en la solicitud:', error);
                if (messageDiv) {
                    messageDiv.textContent = 'No se pudo conectar con el servidor. Intente de nuevo más tarde.';
                    messageDiv.className = 'message error';
                }
            });
        });
    }
});