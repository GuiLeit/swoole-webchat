class UIManager {
    constructor(getChatManager) {
        this.getChatManager = getChatManager;
    }

    renderChatList(chats) {
        const chatList = document.getElementById('chatList');
        chatList.innerHTML = '';

        chats.forEach(chat => {
            const chatItem = this.createChatItem(chat);
            chatList.appendChild(chatItem);
        });
    }

    createChatItem(chat) {
        const chatItem = document.createElement('div');
        chatItem.className = 'chat-item';
        chatItem.dataset.chatId = chat.id;

        const unreadBadge = chat.unreadCount > 0
            ? `<span class="unread-badge">${chat.unreadCount}</span>`
            : '';

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

        chatItem.addEventListener('click', () => this.getChatManager().selectChat(chat.id));

        return chatItem;
    }

    updateChatHeader(activeChat) {
        if (!activeChat) return;

        document.getElementById('headerProfilePic').src = activeChat.avatar;
        document.getElementById('headerContactName').textContent = activeChat.name;
        document.getElementById('headerContactStatus').textContent = 'Online';
    }

    renderMessages(activeChat) {
        if (!activeChat) return;

        const messagesArea = document.getElementById('messagesArea');
        messagesArea.innerHTML = '';

        const messagesContainer = document.createElement('div');
        messagesContainer.className = 'messages-container';

        const dateDivider = document.createElement('div');
        dateDivider.className = 'date-divider';
        dateDivider.innerHTML = `
            <div class="date-divider-content">
                ${activeChat.timestamp.includes('/') ? activeChat.timestamp : 'Hoje'}
            </div>
        `;
        messagesContainer.appendChild(dateDivider);

        activeChat.messages.forEach(message => {
            const messageElement = this.createMessageElement(message);
            messagesContainer.appendChild(messageElement);
        });

        messagesArea.appendChild(messagesContainer);
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }

    createMessageElement(message) {
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
}