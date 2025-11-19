document.addEventListener('DOMContentLoaded', function() {

    // --- VARIABLES GLOBALES Y ELEMENTOS DEL DOM ---
    const conversationsList = document.getElementById('conversations-list');
    const messagesContainer = document.getElementById('messages-container');
    const messageForm = document.getElementById('message-form');
    const messageInput = document.getElementById('message-input');
    const chatWelcomeScreen = document.getElementById('chat-welcome-screen');
    const chatActiveConversation = document.getElementById('chat-active-conversation');
    const chatWithUser = document.getElementById('chat-with-user');
    
    // Modal de nuevo chat
    const newChatBtn = document.getElementById('new-chat-btn');
    const newChatModal = document.getElementById('new-chat-modal');
    const closeModalBtn = newChatModal.querySelector('.close-button');
    const modalSearchUsersInput = document.getElementById('modal-search-users');
    const modalUsersList = document.getElementById('modal-users-list');

    let currentConversationId = null;
    let messagePollingInterval = null;

    // --- FUNCIONES PRINCIPALES ---

    /**
     * Carga las conversaciones del usuario desde el servidor y las muestra.
     */
    async function fetchConversations() {
        try {
            const response = await fetch('api/chat_api.php?action=get_conversations');
            const conversations = await response.json();
            
            conversationsList.innerHTML = ''; // Limpiar lista actual
            if (conversations.success) {
                conversations.data.forEach(conv => {
                    const item = document.createElement('div');
                    item.className = 'conversation-item';
                    item.dataset.conversationId = conv.id;
                    item.innerHTML = `
                        <img src="${conv.participant_avatar || 'assets/default-avatar.png'}" alt="Avatar">
                        <div class="conversation-details">
                            <p class="conversation-name">${conv.participant_name}</p>
                            <p class="last-message">${conv.last_message || ''}</p>
                        </div>
                    `;
                    item.addEventListener('click', () => openConversation(conv.id, conv.participant_name));
                    conversationsList.appendChild(item);
                });
            }
        } catch (error) {
            console.error('Error al cargar conversaciones:', error);
        }
    }

    /**
     * Abre una conversación, muestra su nombre y carga los mensajes.
     */
    function openConversation(conversationId, participantName) {
        currentConversationId = conversationId;

        // Actualizar la UI
        chatWelcomeScreen.style.display = 'none';
        chatActiveConversation.style.display = 'flex';
        chatWithUser.textContent = participantName;

        // Marcar conversación como activa
        document.querySelectorAll('.conversation-item').forEach(item => {
            item.classList.remove('active');
            if (item.dataset.conversationId == conversationId) {
                item.classList.add('active');
            }
        });

        fetchMessages(conversationId);

        // Iniciar el polling para nuevos mensajes
        if (messagePollingInterval) clearInterval(messagePollingInterval);
        messagePollingInterval = setInterval(() => fetchMessages(conversationId, true), 3000); // Revisa cada 3 segundos
    }

    /**
     * Carga los mensajes de una conversación específica.
     * Si onlyNew es true, solo añade mensajes nuevos al final.
     */
    async function fetchMessages(conversationId, onlyNew = false) {
        if (!conversationId) return;
        try {
            const response = await fetch(`api/chat_api.php?action=get_messages&conversation_id=${conversationId}`);
            const result = await response.json();

            if (result.success) {
                if (!onlyNew) messagesContainer.innerHTML = ''; // Limpiar solo si es la carga inicial
                
                const currentMessageCount = messagesContainer.children.length;
                if (result.data.length === currentMessageCount && onlyNew) return; // No hay mensajes nuevos

                const messagesToDisplay = onlyNew ? result.data.slice(currentMessageCount) : result.data;

                messagesToDisplay.forEach(msg => {
                    const messageDiv = document.createElement('div');
                    messageDiv.className = `message ${msg.is_sender ? 'sent' : 'received'}`;
                    messageDiv.textContent = msg.message_content;
                    messagesContainer.appendChild(messageDiv);
                });
                
                // Auto-scroll al último mensaje
                if (messagesToDisplay.length > 0) {
                     messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }
            }
        } catch (error) {
            console.error('Error al cargar mensajes:', error);
        }
    }

    /**
     * Envía un nuevo mensaje al servidor.
     */
    async function sendMessage(e) {
        e.preventDefault();
        const messageText = messageInput.value.trim();
        if (!messageText || !currentConversationId) return;

        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('conversation_id', currentConversationId);
        formData.append('message', messageText);

        try {
            const response = await fetch('api/chat_api.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                messageInput.value = ''; // Limpiar input
                fetchMessages(currentConversationId, true); // Cargar mensajes nuevos de inmediato
            } else {
                console.error('Error al enviar mensaje:', result.message);
            }
        } catch (error) {
            console.error('Error de red al enviar mensaje:', error);
        }
    }
    
    /**
     * Busca usuarios para iniciar una nueva conversación.
     */
    async function searchUsers() {
        const query = modalSearchUsersInput.value.trim();

        // Si el campo está vacío, limpiar la lista y detenerse.
        if (query.length === 0) {
            modalUsersList.innerHTML = '';
            return;
        }

        try {
            const response = await fetch(`api/chat_api.php?action=search_users&query=${query}`);
            const result = await response.json();
            modalUsersList.innerHTML = ''; // Limpiar resultados anteriores

            if (result.success && result.data.length > 0) {
                result.data.forEach(user => {
                    const userDiv = document.createElement('div');
                    userDiv.className = 'user-item';
                    userDiv.textContent = `${user.nombre_completo} (${user.expediente})`;
                    userDiv.addEventListener('click', () => startNewConversation(user.id));
                    modalUsersList.appendChild(userDiv);
                });
            } else {
                // Mostrar un mensaje claro si no hay resultados
                modalUsersList.innerHTML = '<div class="user-item-none">No se encontraron usuarios.</div>';
            }
        } catch (error) {
            console.error('Error al buscar usuarios:', error);
            // Informar al usuario en caso de un error de red o de la API
            modalUsersList.innerHTML = '<div class="user-item-none">Error al realizar la búsqueda.</div>';
        }
    }
    
    /**
     * Inicia una nueva conversación con un usuario.
     */
    async function startNewConversation(userId) {
        const formData = new FormData();
        formData.append('action', 'start_conversation');
        formData.append('user_id', userId);

        try {
            const response = await fetch('api/chat_api.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                // Cerrar modal y refrescar la lista de conversaciones
                newChatModal.style.display = 'none';
                modalSearchUsersInput.value = ''; // Limpiar el campo de búsqueda del modal
                modalUsersList.innerHTML = '';
                fetchConversations();
                // Opcional: abrir la nueva conversación directamente
                // openConversation(result.conversation_id, ...);
            } else {
                console.error('Error al iniciar conversación:', result.message);
            }
        } catch (error) {
            console.error('Error de red al iniciar conversación:', error);
        }
    }


    // --- EVENT LISTENERS ---
    messageForm.addEventListener('submit', sendMessage);
    newChatBtn.addEventListener('click', () => newChatModal.style.display = 'flex');
    closeModalBtn.addEventListener('click', () => newChatModal.style.display = 'none');
    modalSearchUsersInput.addEventListener('input', searchUsers); // Cambiado de 'keyup' a 'input' para mejor respuesta

    window.addEventListener('click', (e) => {
        if (e.target == newChatModal) {
            newChatModal.style.display = 'none';
        }
    });

    // --- INICIALIZACIÓN ---
    fetchConversations();
});
