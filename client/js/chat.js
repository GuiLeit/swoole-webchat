class ChatManager {
    constructor(uiManager, websocketManager) {
        this.chats = [];
        this.activeChat = null;
        this.uiManager = uiManager;
        this.websocketManager = websocketManager;

        // Close active chat on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                // If emoji dropdown is open, let its handler close it first
                const dropdown = document.getElementById('emojiDropdown');
                if (dropdown && dropdown.classList.contains('active')) return;

                if (this.activeChat) {
                    this.activeChat = null;
                    // Remove active highlight from chat list items
                    document.querySelectorAll('.chat-item').forEach(item => item.classList.remove('active'));
                    this.clearChatArea();
                }
            }
        });
    }

    getChatById(chatId) {
        return this.chats.find(chat => chat.id === chatId);
    }

    updateChatsFromConnectedUsers(connectedUsers) {
        const existingChats = [...this.chats];
        this.chats = [];
        
        connectedUsers.forEach(userData => {
            // Convert to User entity
            const user = User.fromServer(userData);
            
            // Skip if this is the current user
            if (window.currentUser && user.id === window.currentUser.id) {
                return;
            }
            
            const existingChat = existingChats.find(chat => chat.userId === user.id);
            
            if (existingChat) {
                this.chats.push(existingChat);
                return;
            } 

            // Generate chat ID using Chat entity method
            const chatId = Chat.generateDmChatId(window.currentUser.id, user.id);
            
            const newChat = {
                id: chatId,
                userId: user.id,
                name: user.username,
                avatar: user.avatar_url,
                lastMessage: "Online agora",
                timestamp: new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
                unreadCount: 0,
                messages: [],
                isOnline: true
            };
            console.log('New chat created:', newChat);
            this.chats.push(newChat);
            
        });
        
        this.uiManager.renderChatList(this.chats);
        
        // Show empty state message if no chats available
        if (this.chats.length === 0) {
            this.showNoUsersMessage();
        }
    }

    showNoUsersMessage() {
        const chatList = document.getElementById('chatList');
        chatList.innerHTML = `
            <div style="padding: 40px 20px; text-align: center; color: #8696a0;">
                <svg viewBox="0 0 24 24" width="80" height="80" style="margin-bottom: 20px; opacity: 0.5;">
                    <path fill="currentColor" d="M12 12.75c1.63 0 3.07.39 4.24.9 1.08.48 1.76 1.56 1.76 2.73V18H6v-1.61c0-1.18.68-2.26 1.76-2.73 1.17-.52 2.61-.91 4.24-.91zM4 13c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm1.13 1.1c-.37-.06-.74-.1-1.13-.1-.99 0-1.93.21-2.78.58A2.01 2.01 0 0 0 0 16.43V18h4.5v-1.61c0-.83.23-1.61.63-2.29zM20 13c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm4 3.43c0-.81-.48-1.53-1.22-1.85A6.95 6.95 0 0 0 20 14c-.39 0-.76.04-1.13.1.4.68.63 1.46.63 2.29V18H24v-1.57zM12 6c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3z"></path>
                </svg>
                <h3 style="color: #e9edef; margin-bottom: 12px; font-size: 18px;">Nenhum usuário online</h3>
                <p style="margin-bottom: 8px; font-size: 14px; line-height: 1.5;">
                    Você está sozinho no chat no momento.
                </p>
                <p style="font-size: 13px; line-height: 1.5;">
                    Para testar o sistema, abra outro navegador ou uma guia anônima, 
                    e faça login com um nome de usuário diferente.
                </p>
            </div>
        `;
    }

    clearChatArea() {
        const messagesArea = document.getElementById('messagesArea');
        messagesArea.innerHTML = `
            <div class="no-chat-selected">
                <div class="welcome-message">
                    <h1>WhatsApp Web Clone</h1>
                    <p>Select a user to start messaging</p>
                </div>
            </div>
        `;
        
        document.getElementById('headerProfilePic').src = 'https://i.pravatar.cc/150?img=1';
        document.getElementById('headerContactName').textContent = 'Select a user';
        document.getElementById('headerContactStatus').textContent = 'Click on a user to start chatting';

        // Hide header and message input when no active chat
        const chatHeader = document.getElementById('chatHeader');
        const messageInputContainer = document.getElementById('messageInputContainer');
        if (chatHeader) chatHeader.classList.add('hidden');
        if (messageInputContainer) messageInputContainer.classList.add('hidden');
    }

    selectChat(chatId) {
        this.activeChat = this.chats.find(chat => chat.id === chatId);

        if (!this.activeChat) {
            this.clearChatArea();
            return;
        }

        this.activeChat.unreadCount = 0;

        if (this.activeChat.userId && this.websocketManager.isConnected()) {
            this.websocketManager.send({
                action: 'get-messages',
                data: {
                    chatId: this.activeChat.id
                }
            });
        }

        document.querySelectorAll('.chat-item').forEach(item => {
            item.classList.remove('active');
            if (parseInt(item.dataset.chatId) === chatId) {
                item.classList.add('active');
            }
        });

        this.uiManager.updateChatHeader(this.activeChat);
        this.uiManager.renderMessages(this.activeChat);
        this.uiManager.renderChatList(this.chats);

        // Show header and message input when a chat is active
        const chatHeader = document.getElementById('chatHeader');
        const messageInputContainer = document.getElementById('messageInputContainer');
        if (chatHeader) chatHeader.classList.remove('hidden');
        if (messageInputContainer) messageInputContainer.classList.remove('hidden');
    }

    handleUserLeft(user) {
        if (this.activeChat && this.activeChat.userId === user.id) {
            this.activeChat = null;
            this.clearChatArea();
        }
    }

    getActiveChat() {
        return this.activeChat;
    }

    getChats() {
        return this.chats;
    }

    getChatByUserId(userId) {
        return this.chats.find(chat => chat.userId === userId);
    }
}