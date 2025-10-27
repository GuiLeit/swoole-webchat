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

        // Create Message entity from incoming data
        const message = new Message({
            id: Date.now().toString(),
            sender_id: senderId,
            chat_id: senderChat.id,
            content: messageText,
            type: 'text',
            timestamp: Math.floor(Date.now() / 1000)
        });

        // Convert to UI format
        const uiMessage = message.toUIFormat(window.currentUser.id);
        uiMessage.timestamp = timestamp; // Use server timestamp for display

        senderChat.messages.push(uiMessage);
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

    handleChatMessages(data) {
        const chatId = data.chatId;
        const messages = data.messages;

        const chat = this.chatManager.getChatById(chatId);
        if (!chat) {
            console.error('No chat found for chatId:', chatId);
            return;
        }

        // Convert server messages to Message entities, then to UI format
        chat.messages = messages.map(msgData => {
            const msg = Message.fromServer(msgData);
            return msg.toUIFormat(window.currentUser.id);
        });

        const activeChat = this.chatManager.getActiveChat();
        if (activeChat && activeChat.id === chat.id) {
            this.uiManager.renderMessages(activeChat);
        }

        console.log('Loaded messages for chat:', chat.name, messages);
    }
}