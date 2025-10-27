/**
 * JavaScript entities matching PHP backend entities
 * Ensures consistent data format between backend and frontend
 */

class User {
    constructor(data) {
        this.id = data.id;
        this.username = data.username;
        this.avatar_url = data.avatar_url || '';
        this.token = data.token || '';
        this.created_at = data.created_at || 0;
    }

    /**
     * Create User from server response
     */
    static fromServer(data) {
        return new User({
            id: data.id,
            username: data.username,
            avatar_url: data.avatar_url,
            token: data.token,
            created_at: data.created_at
        });
    }

    toJSON() {
        return {
            id: this.id,
            username: this.username,
            avatar_url: this.avatar_url,
            token: this.token,
            created_at: this.created_at
        };
    }
}

class Message {
    constructor(data) {
        this.id = data.id;
        this.sender_id = data.sender_id || data.senderId;
        this.chat_id = data.chat_id || data.chatId;
        this.content = data.content || data.text || '';
        this.type = data.type || 'text';
        this.timestamp = data.timestamp || Math.floor(Date.now() / 1000);
    }

    /**
     * Create Message from server response
     */
    static fromServer(data) {
        return new Message({
            id: data.id,
            sender_id: data.sender_id,
            chat_id: data.chat_id,
            content: data.content,
            type: data.type || 'text',
            timestamp: data.timestamp
        });
    }

    /**
     * Get formatted timestamp for display (HH:MM)
     */
    getFormattedTime() {
        const date = new Date(this.timestamp * 1000);
        return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }

    /**
     * Check if this message was sent by current user
     */
    isSentByUser(userId) {
        return this.sender_id === userId;
    }

    /**
     * Convert to UI format for rendering
     */
    toUIFormat(currentUserId) {
        return {
            id: this.id,
            sender: this.isSentByUser(currentUserId) ? 'sent' : 'received',
            text: this.content,
            timestamp: this.getFormattedTime(),
            status: 'received',
            senderId: this.sender_id
        };
    }

    toJSON() {
        return {
            id: this.id,
            sender_id: this.sender_id,
            chat_id: this.chat_id,
            content: this.content,
            type: this.type,
            timestamp: this.timestamp
        };
    }

    isValid() {
        const allowedTypes = ['text', 'image', 'file', 'audio'];
        return this.content && this.content.length > 0 && allowedTypes.includes(this.type);
    }
}

class Chat {
    constructor(data) {
        this.id = data.id;
        this.type = data.type || 'dm';
        this.user_a = data.user_a;
        this.user_b = data.user_b;
        this.created_at = data.created_at || Math.floor(Date.now() / 1000);
        this.last_message = data.last_message || '';
        this.last_timestamp = data.last_timestamp || 0;
    }

    /**
     * Create Chat from server response
     */
    static fromServer(data) {
        return new Chat({
            id: data.id,
            type: data.type,
            user_a: data.user_a,
            user_b: data.user_b,
            created_at: data.created_at,
            last_message: data.last_message,
            last_timestamp: data.last_timestamp
        });
    }

    /**
     * Generate DM chat ID from two user IDs (matches PHP logic)
     */
    static generateDmChatId(userA, userB) {
        const pair = [userA, userB].sort();
        return `chat-${pair[0]}-dm-${pair[1]}`;
    }

    /**
     * Extract user IDs from DM chat ID (matches PHP logic)
     */
    static getUsersByDmChatId(chatId) {
        const parts = chatId.split('-');
        if (parts.length === 4 && parts[2] === 'dm') {
            return [parts[1], parts[3]];
        }
        return [];
    }

    /**
     * Get the other user in a DM chat
     */
    static getOtherUserInDm(chatId, userId) {
        const users = Chat.getUsersByDmChatId(chatId);
        if (users.length !== 2) return null;
        return users[0] === userId ? users[1] : (users[1] === userId ? users[0] : null);
    }

    /**
     * Check if user belongs to this chat
     */
    userBelongsToChat(userId) {
        return this.user_a === userId || this.user_b === userId;
    }

    /**
     * Get the other user's ID in this DM
     */
    getOtherUserId(currentUserId) {
        return this.user_a === currentUserId ? this.user_b : this.user_a;
    }

    /**
     * Get formatted timestamp for display
     */
    getFormattedTime() {
        if (!this.last_timestamp) {
            return new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }
        const date = new Date(this.last_timestamp * 1000);
        return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }

    toJSON() {
        return {
            id: this.id,
            type: this.type,
            user_a: this.user_a,
            user_b: this.user_b,
            created_at: this.created_at,
            last_message: this.last_message,
            last_timestamp: this.last_timestamp
        };
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { User, Message, Chat };
}
