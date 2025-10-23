class ChatManager {
    constructor(uiManager) {
        this.chats = [];
        this.activeChat = null;
        this.nextChatId = 1000;
        this.uiManager = uiManager;
    }

    updateChatsFromConnectedUsers(connectedUsers) {
        const existingChats = [...this.chats];
        this.chats = [];
        
        connectedUsers.forEach(user => {
            // Skip if this is the current user
            if (window.currentUser && user.id === window.currentUser.id) {
                return;
            }
            
            const existingChat = existingChats.find(chat => chat.userId === user.id);
            
            if (existingChat) {
                this.chats.push(existingChat);
                return;
            } 

            const newChat = {
                id: this.nextChatId++,
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
    }

    selectChat(chatId) {
        this.activeChat = this.chats.find(chat => chat.id === chatId);

        if (!this.activeChat) {
            this.clearChatArea();
            return;
        }

        this.activeChat.unreadCount = 0;

        document.querySelectorAll('.chat-item').forEach(item => {
            item.classList.remove('active');
            if (parseInt(item.dataset.chatId) === chatId) {
                item.classList.add('active');
            }
        });

        this.uiManager.updateChatHeader(this.activeChat);
        this.uiManager.renderMessages(this.activeChat);
        this.uiManager.renderChatList(this.chats);
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