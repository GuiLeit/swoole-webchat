const apiConfig = {
    websocketUrl: 'ws://localhost:9501',
    reconnectInterval: 5000,
    maxReconnectAttempts: 5
};

let connectedUsers = [];

function connectWebSocket() {
    try {
        websocket = new WebSocket(apiConfig.websocketUrl);

        websocket.onopen = function (event) {
            console.log('WebSocket connected');
        };

        websocket.onmessage = function (event) {
            try {
                const data = JSON.parse(event.data);
                handleWebSocketMessage(data);
            } catch (error) {
                console.error('Error parsing WebSocket message:', error);
            }
        };

        websocket.onclose = function (event) {
            console.log('WebSocket disconnected');
            // updateConnectionStatus('disconnected', 'Desconectado');

            // Attempt to reconnect
            setTimeout(() => {
                connectWebSocket();
            }, apiConfig.reconnectInterval);
        };

        websocket.onerror = function (error) {
            console.error('WebSocket error:', error);
            // updateConnectionStatus('disconnected', 'Erro de conexão');
        };

    } catch (error) {
        console.error('Error creating WebSocket connection:', error);
        // updateConnectionStatus('disconnected', 'Falha na conexão');
    }
}
connectWebSocket();

async function handleWebSocketMessage(data) {
    console.log('WebSocket message received:', data);
    switch (data.type) {
        case 'welcome':
            break;
        case 'new-message':
        case 'receive-message':
            handleIncomingMessage(data);
            break;
        case 'user-joined':
            handleUserJoined(data.user);
            break;
        case 'user-left':
            handleUserLeft(data.user);
            break;
        case 'users-list':
            handleUsersList(data.users);
            break;
        default:
            console.log('Unknown message type:', data);
    }
}

function handleUserJoined(user) {
    if (!connectedUsers.find(u => u.id === user.id)) {
        connectedUsers.push(user);
        console.log('User joined:', user);
        updateChatsFromConnectedUsers();
    }
}

function handleUserLeft(user) {
    connectedUsers = connectedUsers.filter(u => u.id !== user.id);
    console.log('User left:', user);
    updateChatsFromConnectedUsers();
    
    // If the active chat is with the user who left, clear it
    if (activeChat && activeChat.userId === user.id) {
        activeChat = null;
        clearChatArea();
    }
}

function handleUsersList(users) {
    connectedUsers = users;
    console.log('Users list updated:', users);
    updateChatsFromConnectedUsers();
}

let chats = [];
let activeChat = null;
let nextChatId = 1000; // Start new chat IDs from 1000 to avoid conflicts

// Update chats array based on connected users
function updateChatsFromConnectedUsers() {
    // Clear existing chats and rebuild from connected users
    const existingChats = [...chats]; // Keep existing chat data
    chats = [];
    
    connectedUsers.forEach(user => {
        // Check if we already have a chat with this user
        const existingChat = existingChats.find(chat => chat.userId === user.id);
        
        if (existingChat) {
            // Keep existing chat data (messages, etc.)
            chats.push(existingChat);
        } else {
            // Create new chat for this user
            const newChat = {
                id: nextChatId++,
                userId: user.id,
                name: user.name,
                avatar: user.avatarUrl,
                lastMessage: "Online agora",
                timestamp: new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
                unreadCount: 0,
                messages: [],
                isOnline: true
            };
            chats.push(newChat);
        }
    });
    
    renderChatList();
}

// Clear chat area when no chat is selected
function clearChatArea() {
    const messagesArea = document.getElementById('messagesArea');
    messagesArea.innerHTML = `
        <div class="no-chat-selected">
            <div class="welcome-message">
                <h1>WhatsApp Web Clone</h1>
                <p>Select a user to start messaging</p>
            </div>
        </div>
    `;
    
    // Update header
    document.getElementById('headerProfilePic').src = 'https://i.pravatar.cc/150?img=1';
    document.getElementById('headerContactName').textContent = 'Select a user';
    document.getElementById('headerContactStatus').textContent = 'Click on a user to start chatting';
}

// Initialize the app
document.addEventListener('DOMContentLoaded', () => {
    setupMessageInput();
    clearChatArea(); // Show initial welcome screen
});

// Render chat list in sidebar
function renderChatList() {
    const chatList = document.getElementById('chatList');
    chatList.innerHTML = '';

    chats.forEach(chat => {
        const chatItem = createChatItem(chat);
        chatList.appendChild(chatItem);
    });
}

// Create individual chat item element
function createChatItem(chat) {
    const chatItem = document.createElement('div');
    chatItem.className = 'chat-item';
    chatItem.dataset.chatId = chat.id;

    // Check if this chat has unread messages
    const unreadBadge = chat.unreadCount > 0
        ? `<span class="unread-badge">${chat.unreadCount}</span>`
        : '';

    // Check if last message was sent by user (for checkmark)
    const isLastMessageSent = chat.messages.length > 0 &&
        chat.messages[chat.messages.length - 1].sender === 'sent';

    const readStatus = isLastMessageSent ? `
        <span class="read-status">
            <svg viewBox="0 0 16 15" width="16" height="15">
                <path fill="currentColor" d="M15.01 3.316l-.478-.372a.365.365 0 0 0-.51.063L8.666 9.879a.32.32 0 0 1-.484.033l-.358-.325a.319.319 0 0 0-.484.032l-.378.483a.418.418 0 0 0 .036.541l1.32 1.266c.143.14.361.125.484-.033l6.272-8.048a.366.366 0 0 0-.064-.512zm-4.1 0l-.478-.372a.365.365 0 0 0-.51.063L4.566 9.879a.32.32 0 0 1-.484.033L1.891 7.769a.366.366 0 0 0-.515.006l-.423.433a.364.364 0 0 0 .006.514l3.258 3.185c.143.14.361.125.484-.033l6.272-8.048a.365.365 0 0 0-.063-.51z"></path>
            </svg>
        </span>
    ` : '';

    chatItem.innerHTML = `
        <div class="chat-item-avatar">
            <img src="${chat.avatar}" alt="${chat.name}" class="profile-pic">
            ${unreadBadge}
        </div>
        <div class="chat-item-content">
            <div class="chat-item-header">
                <span class="chat-item-name">${chat.name}</span>
                <span class="chat-item-time">${chat.timestamp}</span>
            </div>
            <div class="chat-item-message">
                ${readStatus}
                <span>${chat.lastMessage}</span>
            </div>
        </div>
    `;

    chatItem.addEventListener('click', () => selectChat(chat.id));

    return chatItem;
}

// Select and display a chat
function selectChat(chatId) {
    activeChat = chats.find(chat => chat.id === chatId);

    if (!activeChat) {
        clearChatArea();
        return;
    }

    // Reset unread count for selected chat
    activeChat.unreadCount = 0;

    // Update active state in chat list
    document.querySelectorAll('.chat-item').forEach(item => {
        item.classList.remove('active');
        if (parseInt(item.dataset.chatId) === chatId) {
            item.classList.add('active');
        }
    });

    // Update chat header
    updateChatHeader();

    // Render messages
    renderMessages();
    
    // Re-render chat list to update unread count display
    renderChatList();
}

// Update the chat header with selected contact info
function updateChatHeader() {
    if (!activeChat) return;

    document.getElementById('headerProfilePic').src = activeChat.avatar;
    document.getElementById('headerContactName').textContent = activeChat.name;
    document.getElementById('headerContactStatus').textContent = 'Online';
}

// Render messages in the main chat area
function renderMessages() {
    if (!activeChat) return;

    const messagesArea = document.getElementById('messagesArea');
    messagesArea.innerHTML = '';

    const messagesContainer = document.createElement('div');
    messagesContainer.className = 'messages-container';

    // Add date divider
    const dateDivider = document.createElement('div');
    dateDivider.className = 'date-divider';
    dateDivider.innerHTML = `
        <div class="date-divider-content">
            ${activeChat.timestamp.includes('/') ? activeChat.timestamp : 'Hoje'}
        </div>
    `;
    messagesContainer.appendChild(dateDivider);

    // Add messages
    activeChat.messages.forEach(message => {
        const messageElement = createMessageElement(message);
        messagesContainer.appendChild(messageElement);
    });

    messagesArea.appendChild(messagesContainer);

    // Scroll to bottom
    messagesArea.scrollTop = messagesArea.scrollHeight;
}

// Create individual message element
function createMessageElement(message) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${message.sender}`;

    const statusIcon = message.sender === 'sent' ? `
        <div class="message-status">
            <svg viewBox="0 0 16 15" width="16" height="15">
                <path fill="currentColor" d="M15.01 3.316l-.478-.372a.365.365 0 0 0-.51.063L8.666 9.879a.32.32 0 0 1-.484.033l-.358-.325a.319.319 0 0 0-.484.032l-.378.483a.418.418 0 0 0 .036.541l1.32 1.266c.143.14.361.125.484-.033l6.272-8.048a.366.366 0 0 0-.064-.512zm-4.1 0l-.478-.372a.365.365 0 0 0-.51.063L4.566 9.879a.32.32 0 0 1-.484.033L1.891 7.769a.366.366 0 0 0-.515.006l-.423.433a.364.364 0 0 0 .006.514l3.258 3.185c.143.14.361.125.484-.033l6.272-8.048a.365.365 0 0 0-.063-.51z"></path>
            </svg>
        </div>
    ` : '';

    messageDiv.innerHTML = `
        <div class="message-bubble">
            <div class="message-text">${message.text}</div>
            <div class="message-meta">
                <span class="message-time">${message.timestamp}</span>
                ${statusIcon}
            </div>
        </div>
    `;

    return messageDiv;
}

// Initialize the app
document.addEventListener('DOMContentLoaded', () => {
    setupMessageInput();
    clearChatArea(); // Show initial welcome screen
});

// Setup message input handling
function setupMessageInput() {
    const messageInput = document.querySelector('.message-input');
    if (!messageInput) return;

    messageInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && e.target.value.trim() && activeChat) {
            sendMessage(e.target.value.trim());
            e.target.value = '';
        }
    });

    // Add click handler for send button if it exists
    const sendButton = document.querySelector('.message-input-container .icon-btn:last-child');
    if (sendButton) {
        sendButton.addEventListener('click', () => {
            const message = messageInput.value.trim();
            if (message && activeChat) {
                sendMessage(message);
                messageInput.value = '';
            }
        });
    }
}

// Send a message
function sendMessage(messageText) {
    if (!activeChat || !messageText.trim()) return;

    const currentTime = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    
    // Create new message object
    const newMessage = {
        id: Date.now(), // Simple ID generation
        sender: 'sent',
        text: messageText,
        timestamp: currentTime,
        status: 'sending'
    };

    // Add message to active chat
    activeChat.messages.push(newMessage);
    activeChat.lastMessage = messageText;
    activeChat.timestamp = currentTime;
    activeChat.isNewChat = false; // Mark as no longer new

    // Update UI
    renderMessages();
    renderChatList();

    // Send via WebSocket if user chat (not static chat)
    if (activeChat.userId && websocket && websocket.readyState === WebSocket.OPEN) {
        const messageData = {
            type: 'send-message',
            recipientId: activeChat.userId,
            message: messageText,
            timestamp: currentTime
        };

        websocket.send(JSON.stringify(messageData));
        console.log('Message sent via WebSocket:', messageData);
        
        // Update message status to sent
        newMessage.status = 'sent';
        renderMessages();
    } else {
        console.log('Message sent locally (static chat):', messageText);
        // For static chats, just mark as sent
        newMessage.status = 'sent';
        renderMessages();
    }
}

// Handle incoming messages from WebSocket
function handleIncomingMessage(data) {
    const senderId = data.senderId;
    const messageText = data.message;
    const timestamp = data.timestamp;

    // Find chat for sender (should exist since connected users become chats)
    let senderChat = chats.find(chat => chat.userId === senderId);
    
    if (!senderChat) {
        console.error('No chat found for sender:', senderId);
        return;
    }

    // Add incoming message
    const incomingMessage = {
        id: Date.now(),
        sender: 'received',
        text: messageText,
        timestamp: timestamp,
        status: 'received'
    };

    senderChat.messages.push(incomingMessage);
    senderChat.lastMessage = messageText;
    senderChat.timestamp = timestamp;

    // Increment unread count if not active chat
    if (!activeChat || activeChat.id !== senderChat.id) {
        senderChat.unreadCount = (senderChat.unreadCount || 0) + 1;
    }

    // Update UI
    renderChatList();
    if (activeChat && activeChat.id === senderChat.id) {
        renderMessages();
    }

    console.log('Received message from:', senderChat.name, messageText);
}
