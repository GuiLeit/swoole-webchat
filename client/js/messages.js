class MessageManager {
    constructor(chatManager, uiManager, websocketManager) {
        this.chatManager = chatManager;
        this.uiManager = uiManager;
        this.websocketManager = websocketManager;
    }

    setupMessageInput() {
        const messageInput = document.querySelector('.message-input');
        if (!messageInput) return;

        messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && e.target.value.trim() && this.chatManager.getActiveChat()) {
                this.sendMessage(e.target.value.trim());
                e.target.value = '';
            }
        });

        const sendButton = document.querySelector('.message-input-container .icon-btn:last-child');
        if (sendButton) {
            sendButton.addEventListener('click', () => {
                const message = messageInput.value.trim();
                if (message && this.chatManager.getActiveChat()) {
                    this.sendMessage(message);
                    messageInput.value = '';
                }
            });
        }
    }

    sendMessage(messageText) {
        const activeChat = this.chatManager.getActiveChat();
        if (!activeChat || !messageText.trim()) return;

        const currentTime = new Date();
        
        const newMessage = {
            id: Date.now(),
            sender: 'sent',
            text: messageText,
            timestamp: currentTime.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
            status: 'sending'
        };

        activeChat.messages.push(newMessage);
        activeChat.lastMessage = messageText;
        activeChat.timestamp = currentTime.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        activeChat.isNewChat = false;

        this.uiManager.renderMessages(activeChat);
        this.uiManager.renderChatList(this.chatManager.getChats());

        if (activeChat.userId && this.websocketManager.isConnected()) {
            const messageData = {
                action: 'send-message',
                data: {
                    chatId: activeChat.id,
                    content: messageText,
                    timestamp: currentTime.toLocaleString('pt-BR')
                }
            };

            if (this.websocketManager.send(messageData)) {
                console.log('Message sent via WebSocket:', messageData);
                newMessage.status = 'sent';
                this.uiManager.renderMessages(activeChat);
            }
        } else {
            console.log('Message sent locally (static chat):', messageText);
            newMessage.status = 'sent';
            this.uiManager.renderMessages(activeChat);
        }
    }

    handleIncomingMessage(data) {
        const senderId = data.senderId;
        const messageText = data.message;
        const timestamp = data.timestamp;

        let senderChat = this.chatManager.getChatByUserId(senderId);
        
        if (!senderChat) {
            console.error('No chat found for sender:', senderId);
            return;
        }

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

        const activeChat = this.chatManager.getActiveChat();
        if (!activeChat || activeChat.id !== senderChat.id) {
            senderChat.unreadCount = (senderChat.unreadCount || 0) + 1;
        }

        this.uiManager.renderChatList(this.chatManager.getChats());
        if (activeChat && activeChat.id === senderChat.id) {
            this.uiManager.renderMessages(activeChat);
        }

        console.log('Received message from:', senderChat.name, messageText);
    }
}