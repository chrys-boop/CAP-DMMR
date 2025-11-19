document.addEventListener('DOMContentLoaded', () => {

    // --- ESTADO DE LA APLICACIÓN ---
    let currentConversationId = null;
    let pollingMessages = null;
    let groupCreationMembers = new Map(); // [ID -> {id, nombre_completo}]

    // --- ELEMENTOS DEL DOM ---
    const sidebar = document.querySelector('.sidebar');
    const chatArea = document.querySelector('.chat-area');
    const conversationsList = document.getElementById('conversations-list');
    const messagesContainer = document.getElementById('messages-container');
    const messageForm = document.getElementById('message-form');
    const messageInput = document.getElementById('message-input');
    const chatWelcomeScreen = document.getElementById('chat-welcome-screen');
    const chatActiveConversation = document.getElementById('chat-active-conversation');
    const chatHeader = document.querySelector('.chat-header');
    const chatWithUser = document.getElementById('chat-with-user');
    const chatWithAvatar = document.getElementById('chat-with-avatar');

    // --- MODALES (Obtenidos de forma segura) ---
    const newChatBtn = document.getElementById('new-chat-btn');
    const newChatModal = document.getElementById('new-chat-modal');
    const closeModalBtn = document.querySelector('#new-chat-modal .close-button');
    const modalSearchUsersInput = document.getElementById('modal-search-users');
    const modalUsersList = document.getElementById('modal-users-list');

    const createGroupBtn = document.getElementById('create-group-btn');
    const createGroupModal = document.getElementById('create-group-modal');
    const closeGroupModalBtn = document.querySelector('#create-group-modal .close-button');
    const groupNameInput = document.getElementById('group-name-input');
    const groupSelectedMembersContainer = document.getElementById('group-selected-members');
    const groupModalSearchInput = document.getElementById('group-modal-search-users');
    const groupModalUsersList = document.getElementById('group-modal-users-list');
    const submitCreateGroupBtn = document.getElementById('submit-create-group');

    /* =======================================================
       1. CARGA DE CONVERSACIONES
    ======================================================= */
    async function fetchConversations() {
        try {
            const res = await fetch("api/chat_api.php?action=get_conversations");
            const data = await res.json();

            if (!data.success) {
                if(conversationsList) conversationsList.innerHTML = `<p class="error-msg">${data.message || 'Error al cargar chats.'}</p>`;
                return;
            }

            const prevActiveId = document.querySelector(".conversation-item.active")?.dataset.conversationId;
            if(conversationsList) conversationsList.innerHTML = "";

            if (data.data.length === 0) {
                if(conversationsList) conversationsList.innerHTML = `<p class="info-msg">No tienes conversaciones. ¡Inicia una nueva!</p>`;
            }

            data.data.forEach(conv => {
                const item = document.createElement("div");
                item.className = "conversation-item";
                item.dataset.conversationId = conv.id;

                const avatar = conv.is_group ? `https://ui-avatars.com/api/?name=${encodeURIComponent(conv.group_name)}&background=random` : conv.participant_avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(conv.participant_name)}&background=random`;
                const name = conv.is_group ? conv.group_name : conv.participant_name;
                const lastMsg = conv.last_message || "Sin mensajes aún";

                item.innerHTML = `
                    <img src="${avatar}" alt="Avatar">
                    <div class="conversation-details">
                        <div class="conversation-name">${name}</div>
                        <div class="last-message">${lastMsg}</div>
                    </div>
                `;

                item.onclick = () => openConversation(conv.id, name, avatar);
                if (String(prevActiveId) === String(conv.id)) item.classList.add("active");
                if(conversationsList) conversationsList.appendChild(item);
            });

        } catch (e) {
            console.error("Error fatal cargando conversaciones:", e);
            if(conversationsList) conversationsList.innerHTML = `<p class="error-msg">Error de conexión con la API.</p>`;
        }
    }

    /* =======================================================
       2. ABRIR UNA CONVERSACIÓN
    ======================================================= */
    function openConversation(id, name, avatar) {
        if (window.innerWidth <= 800 && sidebar) sidebar.classList.remove('open');
        if (currentConversationId === id && chatActiveConversation && chatActiveConversation.style.display === 'flex') return;

        currentConversationId = id;
        if(chatWelcomeScreen) chatWelcomeScreen.style.display = "none";
        if(chatActiveConversation) chatActiveConversation.style.display = "flex";
        if(chatWithUser) chatWithUser.textContent = name;
        if(chatWithAvatar) chatWithAvatar.src = avatar;

        document.querySelectorAll(".conversation-item.active").forEach(c => c.classList.remove("active"));
        const activeItem = document.querySelector(`[data-conversation-id="${id}"]`);
        if (activeItem) activeItem.classList.add("active");

        if (pollingMessages) clearInterval(pollingMessages);
        fetchMessages(id).then(() => {
            pollingMessages = setInterval(() => fetchMessages(id, true), 3000);
        });
    }

    /* =======================================================
       3. CARGAR MENSAJES
    ======================================================= */
    async function fetchMessages(conversationId, onlyNew = false) {
        if (!messagesContainer) return;
        try {
            const res = await fetch(`api/chat_api.php?action=get_messages&conversation_id=${conversationId}`);
            const data = await res.json();
            if (!data.success) return;

            const wasScrolledToBottom = messagesContainer.scrollTop + messagesContainer.clientHeight >= messagesContainer.scrollHeight - 50;
            if (!onlyNew) messagesContainer.innerHTML = "";

            const existingIds = new Set([...messagesContainer.querySelectorAll("[data-id]")].map(m => m.dataset.id));
            data.data.forEach(msg => {
                if (existingIds.has(String(msg.id))) return;
                const div = document.createElement("div");
                div.className = `message ${msg.is_sender ? "sent" : "received"}`;
                div.dataset.id = msg.id;
                div.innerHTML = `<div class="bubble-text">${msg.message_content}</div><div class="message-timestamp">${msg.timestamp_formatted || ''}</div>`;
                messagesContainer.appendChild(div);
            });

            if (wasScrolledToBottom || !onlyNew) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        } catch (e) { console.error("Error cargando mensajes:", e); }
    }

    /* =======================================================
       4. ENVIAR MENSAJE
    ======================================================= */
    async function sendMessage(e) {
        e.preventDefault();
        if (!messageInput || !messageInput.value.trim() || !currentConversationId) return;
        const text = messageInput.value.trim();
        messageInput.value = "";
        
        const tempId = `temp_${Date.now()}`;
        const div = document.createElement("div");
        div.className = 'message sent optimistic';
        div.dataset.id = tempId;
        div.innerHTML = `<div class="bubble-text">${text}</div><div class="message-timestamp">Enviando...</div>`;
        messagesContainer.appendChild(div);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;

        const form = new FormData();
        form.append("action", "send_message");
        form.append("conversation_id", currentConversationId);
        form.append("message", text);

        try {
            const res = await fetch("api/chat_api.php", { method: "POST", body: form });
            const data = await res.json();
            const tempMessage = messagesContainer.querySelector(`[data-id='${tempId}']`);
            if (!tempMessage) return;

            if (data.success && data.data) {
                tempMessage.dataset.id = data.data.id;
                tempMessage.classList.remove('optimistic');
                tempMessage.querySelector('.message-timestamp').textContent = data.data.timestamp_formatted;
                fetchConversations(); // Actualiza la lista para que este chat suba
            } else {
                tempMessage.classList.add('error');
                tempMessage.querySelector('.message-timestamp').textContent = 'Error al enviar';
            }
        } catch (e) {
            console.error('Error en send_message:', e);
            const tempMessage = messagesContainer.querySelector(`[data-id='${tempId}']`);
            if (tempMessage) tempMessage.querySelector('.message-timestamp').textContent = 'Error de red';
        }
    }

    /* =======================================================
       5. BUSCAR USUARIOS (PARA CHAT INDIVIDUAL Y GRUPO)
    ======================================================= */
    async function searchUsers(query, resultContainer, exclusionMap = new Map()) {
        if (!query) {
            resultContainer.innerHTML = "";
            return;
        }
        try {
            const res = await fetch(`api/chat_api.php?action=search_users&query=${encodeURIComponent(query)}`);
            const data = await res.json();
            resultContainer.innerHTML = "";
            if (!data.success || data.data.length === 0) {
                resultContainer.innerHTML = "<div class='user-item-none'>Sin resultados</div>";
                return;
            }
            data.data.forEach(u => {
                if (exclusionMap.has(u.id)) return;
                const div = document.createElement("div");
                div.className = "user-item";
                div.innerHTML = `<strong>${u.nombre_completo}</strong> <span>(${u.expediente})</span>`;
                // Dependiendo del contenedor, la acción es diferente
                if (resultContainer.id === 'modal-users-list') {
                     div.onclick = () => startConversation(u.id, u.nombre_completo, u.avatar_url);
                } else {
                     div.onclick = () => addMemberToGroupSelection(u);
                }
                resultContainer.appendChild(div);
            });
        } catch (e) { resultContainer.innerHTML = "<div class='user-item-none'>Error de búsqueda</div>"; }
    }

    /* =======================================================
       6. INICIAR CONVERSACIÓN INDIVIDUAL
    ======================================================= */
    async function startConversation(userId, userName, avatar) {
        const form = new FormData();
        form.append("action", "start_conversation");
        form.append("user_id", userId);
        try {
            const res = await fetch("api/chat_api.php", { method: "POST", body: form });
            const data = await res.json();
            if (!data.success) { alert(data.message); return; }

            if (newChatModal) newChatModal.style.display = "none";
            await fetchConversations();
            const finalAvatar = avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=random`;
            openConversation(data.conversation_id, userName, finalAvatar);
        } catch(e) { alert("Error de red al iniciar la conversación."); }
    }

    /* =======================================================
       7. FUNCIONALIDAD DE CREACIÓN DE GRUPOS
    ======================================================= */
    function openGroupCreationModal() {
        groupCreationMembers.clear();
        if(groupNameInput) groupNameInput.value = "";
        if(groupModalSearchInput) groupModalSearchInput.value = "";
        if(groupModalUsersList) groupModalUsersList.innerHTML = "";
        renderSelectedMembers();
        if(createGroupModal) createGroupModal.style.display = "flex";
    }

    function addMemberToGroupSelection(user) {
        if (groupCreationMembers.has(user.id)) return;
        groupCreationMembers.set(user.id, user);
        if(groupModalSearchInput) groupModalSearchInput.value = "";
        if(groupModalUsersList) groupModalUsersList.innerHTML = "";
        renderSelectedMembers();
    }

    function removeMemberFromGroupSelection(userId) {
        groupCreationMembers.delete(userId);
        renderSelectedMembers();
    }

    function renderSelectedMembers() {
        if(!groupSelectedMembersContainer) return;
        groupSelectedMembersContainer.innerHTML = "";
        if (groupCreationMembers.size === 0) {
            groupSelectedMembersContainer.innerHTML = "<p class='info-msg'>Añade miembros desde la búsqueda.</p>";
            return;
        }
        groupCreationMembers.forEach(user => {
            const item = document.createElement('div');
            item.className = 'selected-member-item';
            item.innerHTML = `<span>${user.nombre_completo}</span><button class="remove-member-btn" data-id="${user.id}">&times;</button>`;
            // Añadir el listener al botón de eliminar miembro de forma segura
            const removeBtn = item.querySelector('.remove-member-btn');
            if (removeBtn) removeBtn.onclick = () => removeMemberFromGroupSelection(user.id);
            groupSelectedMembersContainer.appendChild(item);
        });
    }

    async function submitCreateGroup() {
        if(!groupNameInput) return;
        const groupName = groupNameInput.value.trim();
        if (!groupName) {
            alert("Por favor, dale un nombre al grupo.");
            return;
        }
        if (groupCreationMembers.size < 1) {
            alert("Debes añadir al menos un miembro al grupo.");
            return;
        }

        const memberIds = Array.from(groupCreationMembers.keys());
        const form = new FormData();
        form.append('action', 'create_group');
        form.append('name', groupName);
        form.append('members', JSON.stringify(memberIds));

        try {
            const res = await fetch("api/chat_api.php", { method: "POST", body: form });
            const data = await res.json();

            if (data.success) {
                if(createGroupModal) createGroupModal.style.display = 'none';
                await fetchConversations();
                openConversation(data.conversation_id, groupName, `https://ui-avatars.com/api/?name=${encodeURIComponent(groupName)}&background=random`);
            } else {
                alert(`Error al crear grupo: ${data.message}`);
            }
        } catch(e) { alert('Error de red al crear el grupo.'); }
    }

    /* =======================================================
       8. EVENT LISTENERS (VERSIÓN FINAL Y ROBUSTA)
    ======================================================= */
    if (messageForm) {
        messageForm.addEventListener("submit", sendMessage);
    }
    
    // --- Modal de chat individual ---
    if (newChatBtn && newChatModal) {
        newChatBtn.onclick = () => { newChatModal.style.display = 'flex'; };
    }
    if (closeModalBtn && newChatModal) {
        closeModalBtn.onclick = () => { newChatModal.style.display = 'none'; };
    }
    if (modalSearchUsersInput && modalUsersList) {
        modalSearchUsersInput.addEventListener('input', () => searchUsers(modalSearchUsersInput.value, modalUsersList));
    }

    // --- Modal de creación de grupo ---
    if (createGroupBtn && createGroupModal) {
        createGroupBtn.onclick = openGroupCreationModal;
    }
    if (closeGroupModalBtn && createGroupModal) {
        closeGroupModalBtn.onclick = () => { createGroupModal.style.display = 'none'; };
    }
    if (groupModalSearchInput && groupModalUsersList) {
        groupModalSearchInput.addEventListener('input', () => searchUsers(groupModalSearchInput.value, groupModalUsersList, groupCreationMembers));
    }
    if (submitCreateGroupBtn) {
        submitCreateGroupBtn.onclick = submitCreateGroup; // CORREGIDO
    }
    
    // --- Clics generales ---
    window.onclick = e => {
        if (newChatModal && e.terget === newChatModal) newChatModal.style.display = 'none';
        if (createGroupModal && e.target === createGroupModal) createGroupModal.style.display = 'none';
    };

    // Carga inicial y sondeo
    fetchConversations();
    setInterval(fetchConversations, 5000);

});