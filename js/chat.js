document.addEventListener('DOMContentLoaded', () => {

    // --- ESTADO DE LA APLICACIÓN ---
    let conversationsCache = new Map();
    let currentConversationId = null;
    let pollingMessages = null;
    let groupCreationMembers = new Map();
    let addMembersSelection = new Map();
    // (NUEVO) Almacenamiento local para mensajes ocultos
    let hiddenMessages = new Set(JSON.parse(localStorage.getItem('hidden_chat_messages')) || []);

    // --- ELEMENTOS DEL DOM -- -
    const conversationsList = document.getElementById('conversations-list');
    const messagesContainer = document.getElementById('messages-container');
    const messageForm = document.getElementById('message-form');
    const messageInput = document.getElementById('message-input');
    const chatWelcomeScreen = document.getElementById('chat-welcome-screen');
    const chatActiveConversation = document.getElementById('chat-active-conversation');
    const chatHeaderActions = document.getElementById('chat-header-actions');
    const chatWithUser = document.getElementById('chat-with-user');
    const chatWithAvatar = document.getElementById('chat-with-avatar');

    // --- MODALES ---
    const newChatModal = document.getElementById('new-chat-modal');
    const createGroupModal = document.getElementById('create-group-modal');
    const addMembersModal = document.getElementById('add-members-modal');
    const viewMembersModal = document.getElementById('view-members-modal');

    // (NUEVO) Función para guardar IDs de mensajes ocultos
    function saveHiddenMessages() {
        localStorage.setItem('hidden_chat_messages', JSON.stringify(Array.from(hiddenMessages)));
    }

    /* =======================================================
       1. CARGA Y RENDERIZADO DE CONVERSACIONES
    ======================================================= */
    async function fetchConversations() {
        try {
            const res = await fetch("api/chat_api.php?action=get_conversations");
            const data = await res.json();

            if (!data.success) {
                if (!res.headers.get("content-type")?.includes("application/json")) {
                     console.error("Respuesta inesperada del servidor. Probablemente la sesión ha expirado.");
                     conversationsList.innerHTML = `<p class="error-msg">Error de conexión. Recarga la página.</p>`;
                     return;
                }
                conversationsList.innerHTML = `<p class="error-msg">${data.message || 'Error al cargar'}</p>`;
                return;
            }

            const newCache = new Map();
            data.data.forEach(conv => newCache.set(conv.id, conv));
            conversationsCache = newCache;
            
            renderConversationsList();

        } catch (e) {
            console.error("Error fatal cargando conversaciones:", e);
            conversationsList.innerHTML = `<p class="error-msg">Error de conexión.</p>`;
        }
    }

    function renderConversationsList() {
        const activeId = currentConversationId;
        conversationsList.innerHTML = "";

        if (conversationsCache.size === 0) {
            conversationsList.innerHTML = `<p class="info-msg">No tienes chats. ¡Inicia uno nuevo!</p>`;
            return;
        }
        
        const sortedConversations = Array.from(conversationsCache.values());

        sortedConversations.forEach(conv => {
            const item = document.createElement("div");
            item.className = "conversation-item";
            item.dataset.conversationId = conv.id;
            if (conv.id === activeId) {
                item.classList.add("active");
            }

            item.innerHTML = `
                <img src="${conv.avatar}" alt="Avatar">
                <div class="conversation-details">
                    <div class="conversation-name">${conv.name}</div>
                    <div class="last-message">${conv.last_message || ''}</div>
                </div>
            `;

            item.onclick = () => openConversation(conv.id);
            conversationsList.appendChild(item);
        });
    }

    /* =======================================================
       2. GESTIÓN DE LA CONVERSACIÓN ACTIVA
    ======================================================= */
    function openConversation(id) {
        if (currentConversationId === id && chatActiveConversation.style.display === 'flex') return;
        
        const conv = conversationsCache.get(id);
        if (!conv) {
            console.error("Conversación no encontrada en caché");
            return;
        }

        currentConversationId = id;
        chatWelcomeScreen.style.display = "none";
        chatActiveConversation.style.display = "flex";
        chatWithUser.textContent = conv.name;
        chatWithAvatar.src = conv.avatar;

        renderChatHeaderActions(conv);
        renderConversationsList();

        if (pollingMessages) clearInterval(pollingMessages);
        fetchMessages(id).then(() => {
            pollingMessages = setInterval(() => fetchMessages(id, true), 3000);
        });
    }

    function renderChatHeaderActions(conv) {
    chatHeaderActions.innerHTML = '';

    if (conv.is_group) {
        // Botón para ver miembros
        const viewBtn = document.createElement('button');
        viewBtn.className = 'header-action-btn';
        viewBtn.title = 'Ver Miembros';
        viewBtn.innerHTML = '👥';
        viewBtn.onclick = () => openViewMembersModal(conv);
        chatHeaderActions.appendChild(viewBtn);

        // Botón para añadir miembros (solo roles autorizados)
        if ([4, 5].includes(USER_ROLE)) {
            const addBtn = document.createElement('button');
            addBtn.className = 'header-action-btn';
            addBtn.title = 'Añadir Miembros';
            addBtn.innerHTML = '&#43;👤';
            addBtn.onclick = () => openAddMembersModal(conv);
            chatHeaderActions.appendChild(addBtn);
        }

        // Botón para abandonar el grupo
        const leaveBtn = document.createElement('button');
        leaveBtn.className = 'header-action-btn';
        leaveBtn.title = 'Abandonar Grupo';
        leaveBtn.innerHTML = '🚪'; // Ícono de puerta
        leaveBtn.onclick = () => leaveGroup(conv.id);
        chatHeaderActions.appendChild(leaveBtn);

    } else {
        // Botón para borrar chat individual
        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'header-action-btn';
        deleteBtn.title = 'Borrar Chat';
        deleteBtn.innerHTML = '🗑️'; // Ícono de papelera
        deleteBtn.onclick = () => deleteChat(conv.id);
        chatHeaderActions.appendChild(deleteBtn);
    }
}


    /* =======================================================
       3. CARGA Y ENVÍO DE MENSAJES
    ======================================================= */
    async function fetchMessages(conversationId, onlyNew = false) {
        try {
            const res = await fetch(`api/chat_api.php?action=get_messages&conversation_id=${conversationId}`);
            const data = await res.json();
            if (!data.success) return;

            const wasScrolledToBottom = messagesContainer.scrollTop + messagesContainer.clientHeight >= messagesContainer.scrollHeight - 50;
            if (!onlyNew) messagesContainer.innerHTML = "";

            const existingIds = new Set([...messagesContainer.querySelectorAll("[data-id]")].map(m => m.dataset.id));
            data.data.forEach(msg => {
                // (MODIFICADO) No renderizar si está oculto localmente
                if (hiddenMessages.has(String(msg.id))) return;
                if (existingIds.has(String(msg.id))) return;

                const div = document.createElement("div");
                div.className = `message ${msg.is_sender ? "sent" : "received"}`;
                div.dataset.id = msg.id;
                div.innerHTML = `<div class="bubble-text">${msg.message_content}</div><div class="message-timestamp">${msg.timestamp_formatted || ''}</div>`;
                
                // (NUEVO) Añadir listener para menú contextual en mensajes enviados
                if (msg.is_sender) {
                    div.addEventListener('click', (e) => showDeleteContextMenu(e, msg.id));
                }

                messagesContainer.appendChild(div);
            });

            if (wasScrolledToBottom || !onlyNew) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        } catch (e) { console.error("Error cargando mensajes:", e); }
    }

    async function sendMessage(e) {
        e.preventDefault();
        const text = messageInput.value.trim();
        if (!text || !currentConversationId) return;
        messageInput.value = "";

        const form = new FormData();
        form.append("action", "send_message");
        form.append("conversation_id", currentConversationId);
        form.append("message", text);

        try {
            const res = await fetch("api/chat_api.php", { method: "POST", body: form });
            const data = await res.json();
            if (data.success) {
                await fetchMessages(currentConversationId, true);
                await fetchConversations();
            } else {
                alert('Error al enviar mensaje: ' + data.message);
            }
        } catch (e) {
            alert('Error de red al enviar el mensaje.');
        }
    }

    /* =======================================================
       4. (NUEVO) MENÚ CONTEXTUAL PARA ELIMINAR MENSAJES
    ======================================================= */
    function showDeleteContextMenu(event, messageId) {
        event.preventDefault();
        closeContextMenu(); // Cierra cualquier otro menú abierto

        const contextMenu = document.createElement('div');
        contextMenu.className = 'message-context-menu';
        contextMenu.id = 'message-context-menu';
        
        // Posicionar el menú donde se hizo clic
        contextMenu.style.left = `${event.pageX}px`;
        contextMenu.style.top = `${event.pageY}px`;

        contextMenu.innerHTML = `
            <button id="delete-for-me">Eliminar para mí</button>
            <button id="delete-for-everyone">Eliminar para todos</button>
        `;

        document.body.appendChild(contextMenu);

        // Lógica para "Eliminar para mí"
        document.getElementById('delete-for-me').onclick = () => {
            hiddenMessages.add(String(messageId));
            saveHiddenMessages();
            const msgElement = document.querySelector(`.message[data-id='${messageId}']`);
            if (msgElement) msgElement.style.display = 'none'; // Ocultarlo de la vista
            closeContextMenu();
        };

        // Lógica para "Eliminar para todos"
        document.getElementById('delete-for-everyone').onclick = async () => {
            if (!confirm("¿Estás seguro de que quieres eliminar este mensaje para todos?")) {
                closeContextMenu();
                return;
            }
            
            const form = new FormData();
            form.append('action', 'delete_message');
            form.append('message_id', messageId);

            try {
                const res = await fetch('api/chat_api.php', { method: 'POST', body: form });
                const data = await res.json();
                if (data.success) {
                    // Si se borra en la DB, eliminarlo de la vista de todos modos
                    const msgElement = document.querySelector(`.message[data-id='${messageId}']`);
                    if (msgElement) msgElement.remove();
                } else {
                    alert(`Error: ${data.message}`);
                }
            } catch (e) {
                alert('Error de red al intentar eliminar el mensaje.');
            }
            closeContextMenu();
        };
    }

    // Cierra el menú contextual
    function closeContextMenu() {
        const existingMenu = document.getElementById('message-context-menu');
        if (existingMenu) existingMenu.remove();
    }
    
    // Cierra el menú si se hace clic fuera
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#message-context-menu') && !e.target.closest('.message.sent')) {
            closeContextMenu();
        }
    });

    /* =======================================================
       5. MODAL: CREAR GRUPO
    ======================================================= */
    const createGroupBtn = document.getElementById('create-group-btn');
    if (createGroupBtn) {
        createGroupBtn.onclick = () => {
            groupCreationMembers.clear();
            document.getElementById('group-name-input').value = '';
            document.getElementById('group-modal-search-users').value = '';
            document.getElementById('group-modal-users-list').innerHTML = '';
            renderSelectedMembers(groupCreationMembers, 'group-selected-members', removeMemberFromGroupSelection);
            createGroupModal.style.display = 'flex';
        };
    }

    document.getElementById('group-modal-search-users').addEventListener('input', (e) => {
        const query = e.target.value;
        const exclusions = Array.from(groupCreationMembers.keys());
        searchUsers(query, document.getElementById('group-modal-users-list'), exclusions, addMemberToGroupSelection);
    });

    document.getElementById('submit-create-group').onclick = async () => {
        const groupName = document.getElementById('group-name-input').value.trim();
        if (!groupName) {
            alert("Por favor, dale un nombre al grupo.");
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
                createGroupModal.style.display = 'none';
                await fetchConversations();
                openConversation(data.conversation_id);
            } else {
                alert(`Error al crear grupo: ${data.message}`);
            }
        } catch (e) {
            alert('Error de red al crear el grupo.');
        }
    };

    function addMemberToGroupSelection(user) {
        groupCreationMembers.set(user.id, user);
        document.getElementById('group-modal-search-users').value = '';
        document.getElementById('group-modal-users-list').innerHTML = '';
        renderSelectedMembers(groupCreationMembers, 'group-selected-members', removeMemberFromGroupSelection);
    }

    function removeMemberFromGroupSelection(userId) {
        groupCreationMembers.delete(userId);
        renderSelectedMembers(groupCreationMembers, 'group-selected-members', removeMemberFromGroupSelection);
    }

    /* =======================================================
       6. MODAL: AÑADIR MIEMBROS A GRUPO
    ======================================================= */
    function openAddMembersModal(conv) {
        addMembersSelection.clear();
        document.getElementById('add-members-group-name').textContent = conv.name;
        document.getElementById('add-members-search-input').value = '';
        document.getElementById('add-members-search-results').innerHTML = '';
        renderSelectedMembers(addMembersSelection, 'add-members-selected-list', removeMemberFromAddSelection);
        addMembersModal.style.display = 'flex';
    }

    document.getElementById('add-members-search-input').addEventListener('input', (e) => {
        const query = e.target.value;
        const conv = conversationsCache.get(currentConversationId);
        if (!conv) return;

        const exclusions = [...conv.participant_ids, ...Array.from(addMembersSelection.keys())];
        searchUsers(query, document.getElementById('add-members-search-results'), exclusions, addMemberToAddSelection);
    });

    document.getElementById('submit-add-members').onclick = async () => {
        const newMemberIds = Array.from(addMembersSelection.keys());
        if (newMemberIds.length === 0) {
            alert('Debes seleccionar al menos un miembro para añadir.');
            return;
        }

        const form = new FormData();
        form.append('action', 'add_members');
        form.append('conversation_id', currentConversationId);
        form.append('members', JSON.stringify(newMemberIds));

        try {
            const res = await fetch('api/chat_api.php', { method: 'POST', body: form });
            const data = await res.json();

            if (data.success) {
                addMembersModal.style.display = 'none';
                await fetchConversations(); 
                alert('Miembros añadidos correctamente.');
            } else {
                alert(`Error al añadir miembros: ${data.message}`);
            }
        } catch (e) {
            alert('Error de red al añadir miembros.');
        }
    };

    function addMemberToAddSelection(user) {
        addMembersSelection.set(user.id, user);
        document.getElementById('add-members-search-input').value = '';
        document.getElementById('add-members-search-results').innerHTML = '';
        renderSelectedMembers(addMembersSelection, 'add-members-selected-list', removeMemberFromAddSelection);
    }

    function removeMemberFromAddSelection(userId) {
        addMembersSelection.delete(userId);
        renderSelectedMembers(addMembersSelection, 'add-members-selected-list', removeMemberFromAddSelection);
    }

    /* =======================================================
       7. MODAL: VER MIEMBROS DE GRUPO
    ======================================================= */
    async function openViewMembersModal(conv) {
        document.getElementById('view-members-group-name').textContent = conv.name;
        const listContainer = document.getElementById('view-members-list');
        listContainer.innerHTML = '<p class="info-msg">Cargando miembros...</p>';
        viewMembersModal.style.display = 'flex';

        try {
            const res = await fetch(`api/chat_api.php?action=get_group_members&conversation_id=${conv.id}`);
            const data = await res.json();

            if (data.success) {
                renderGroupMembersList(data.data);
            } else {
                listContainer.innerHTML = `<p class="error-msg">${data.message || 'Error al cargar la lista.'}</p>`;
            }
        } catch (e) {
            console.error("Error al obtener miembros del grupo:", e);
            listContainer.innerHTML = `<p class="error-msg">Error de conexión.</p>`;
        }
    }

    function renderGroupMembersList(members) {
        const listContainer = document.getElementById('view-members-list');
        listContainer.innerHTML = '';

        if (!members || members.length === 0) {
            listContainer.innerHTML = '<p class="info-msg">No se encontraron miembros.</p>';
            return;
        }

        members.forEach(member => {
            const item = document.createElement('div');
            item.className = 'user-item';
            item.innerHTML = `<strong>${member.nombre_completo}</strong> <span>(${member.expediente || 'N/A'})</span>`;
            listContainer.appendChild(item);
        });
    }

    /* =======================================================
       8. FUNCIONES GENÉRICAS (Búsqueda, etc.)
    ======================================================= */
    async function searchUsers(query, resultContainer, exclusions = [], onSelect) {
        if (!query.trim()) {
            resultContainer.innerHTML = "";
            return;
        }
        try {
            const excludeParam = JSON.stringify(exclusions);
            const res = await fetch(`api/chat_api.php?action=search_users&query=${encodeURIComponent(query)}&exclude=${encodeURIComponent(excludeParam)}`);
            const data = await res.json();
            resultContainer.innerHTML = "";

            if (data.success && data.data.length > 0) {
                data.data.forEach(u => {
                    const div = document.createElement("div");
                    div.className = "user-item";
                    div.innerHTML = `<strong>${u.nombre_completo}</strong> <span>(${u.expediente})</span>`;
                    div.onclick = () => onSelect(u);
                    resultContainer.appendChild(div);
                });
            } else {
                resultContainer.innerHTML = "<div class='user-item-none'>Sin resultados</div>";
            }
        } catch (e) {
            console.error('Error en searchUsers:', e);
            resultContainer.innerHTML = "<div class='user-item-none'>Error de búsqueda</div>";
        }
    }

    function renderSelectedMembers(selectionMap, containerId, onRemove) {
        const container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML = "";
        if (selectionMap.size === 0) {
            container.innerHTML = "<p class='info-msg'>Añade miembros desde la búsqueda.</p>";
            return;
        }
        selectionMap.forEach(user => {
            const item = document.createElement('div');
            item.className = 'selected-member-item';
            item.innerHTML = `<span>${user.nombre_completo}</span><button class="remove-member-btn" data-id="${user.id}">&times;</button>`;
            item.querySelector('.remove-member-btn').onclick = () => onRemove(user.id);
            container.appendChild(item);
        });
    }

async function leaveGroup(conversationId) {
    if (!confirm("¿Estás seguro de que quieres abandonar este grupo?")) return;

    const form = new FormData();
    form.append('action', 'leave_group');
    form.append('conversation_id', conversationId);

    try {
        const res = await fetch('api/chat_api.php', { method: 'POST', body: form });
        const data = await res.json();

        if (data.success) {
            alert("Has abandonado el grupo.");
            currentConversationId = null;
            chatActiveConversation.style.display = 'none';
            chatWelcomeScreen.style.display = 'flex';
            fetchConversations();
        } else {
            alert(`Error al abandonar el grupo: ${data.message}`);
        }
    } catch (e) {
        alert('Error de red al intentar abandonar el grupo.');
    }
}

async function deleteChat(conversationId) {
    if (!confirm("¿Estás seguro de que quieres borrar este chat? Esta acción no se puede deshacer.")) return;

    const form = new FormData();
    form.append('action', 'delete_chat');
    form.append('conversation_id', conversationId);

    try {
        const res = await fetch('api/chat_api.php', { method: 'POST', body: form });
        const data = await res.json();

        if (data.success) {
            alert("Chat borrado correctamente.");
            currentConversationId = null;
            chatActiveConversation.style.display = 'none';
            chatWelcomeScreen.style.display = 'flex';
            fetchConversations();
        } else {
            alert(`Error al borrar el chat: ${data.message}`);
        }
    } catch (e) {
        alert('Error de red al intentar borrar el chat.');
    }
}

    /* =======================================================
       9. EVENT LISTENERS GENERALES
    ======================================================= */
    if (messageForm) {
        messageForm.addEventListener("submit", sendMessage);
    }
    
    const newChatBtn = document.getElementById('new-chat-btn');
    if (newChatBtn) {
        newChatBtn.onclick = () => {
            document.getElementById('modal-search-users').value = '';
            document.getElementById('modal-users-list').innerHTML = '';
            newChatModal.style.display = 'flex';
        };
    }
    
    document.getElementById('modal-search-users').addEventListener('input', (e) => {
        searchUsers(e.target.value, document.getElementById('modal-users-list'), [], async (user) => {
            const form = new FormData();
            form.append('action', 'start_conversation');
            form.append('user_id', user.id);
            try {
                const res = await fetch('api/chat_api.php', { method: 'POST', body: form });
                const data = await res.json();
                if(data.success) {
                    newChatModal.style.display = 'none';
                    await fetchConversations();
                    openConversation(data.conversation_id);
                } else {
                    alert('Error al iniciar chat: ' + data.message);
                }
            } catch (err) {
                alert('Error de red al iniciar chat.');
            }
        });
    });

    document.querySelectorAll('.modal .close-button').forEach(btn => {
        btn.onclick = () => {
            btn.closest('.modal').style.display = 'none';
        };
    });
    window.onclick = e => {
        if (e.target.classList.contains('modal')) {
            e.target.style.display = 'none';
        }
    };
    
    // Carga inicial
    fetchConversations();
    setInterval(fetchConversations, 5000); // Polling para mantener la lista de chats actualizada

});
