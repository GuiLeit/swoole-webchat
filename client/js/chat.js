class ChatManager {
    constructor(uiManager) {
        this.chats = [];
        this.activeChat = null;
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
                id: ChatUtils.generateChatId(window.currentUser.id, user.id),
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

class ChatUtils {
    /**
     * Generate a unique chat ID from two user IDs
     * Always produces the same ID regardless of order
     * @param {string} userId1 - First user ID
     * @param {string} userId2 - Second user ID
     * @returns {string} - Unique chat ID
     */
    static generateChatId(userId1, userId2) {
        // Sort user IDs to ensure consistency
        const sortedIds = [userId1, userId2].sort();
        const combined = `${sortedIds[0]}_${sortedIds[1]}`;
        
        // Generate hash for obfuscation (optional but recommended)
        return this.simpleHash(combined);
    }

    /**
     * Simple hash function for generating chat IDs
     * @param {string} str - String to hash
     * @returns {string} - Hashed string
     */
    static simpleHash(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32bit integer
        }
        return 'chat_' + Math.abs(hash).toString(36);
    }

    /**
     * Alternative: More secure hash using Web Crypto API (async)
     * Use this if you want better security
     */
    static async generateSecureChatId(userId1, userId2) {
        const sortedIds = [userId1, userId2].sort();
        const combined = `${sortedIds[0]}_${sortedIds[1]}`;
        
        const encoder = new TextEncoder();
        const data = encoder.encode(combined);
        const hashBuffer = await crypto.subtle.digest('SHA-256', data);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
        
        // Return first 16 characters for shorter ID
        return 'chat_' + hashHex.substring(0, 16);
    }
}