class WebSocketManager {
    constructor(config) {
        this.config = config;
        this.websocket = null;
        this.connectedUsers = [];
        this.messageHandler = null;
        this.userHandler = null;
    }

    setMessageHandler(handler) {
        this.messageHandler = handler;
    }

    setUserHandler(handler) {
        this.userHandler = handler;
    }

    connect() {
        try {
            this.websocket = new WebSocket(this.config.websocketUrl);

            this.websocket.onopen = (event) => {
                console.log('WebSocket connected');
            };

            this.websocket.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.handleMessage(data);
                } catch (error) {
                    console.error('Error parsing WebSocket message:', error);
                }
            };

            this.websocket.onclose = (event) => {
                console.log('WebSocket disconnected');
                setTimeout(() => {
                    this.connect();
                }, this.config.reconnectInterval);
            };

            this.websocket.onerror = (error) => {
                console.error('WebSocket error:', error);
            };

        } catch (error) {
            console.error('Error creating WebSocket connection:', error);
        }
    }

    handleMessage(data) {
        console.log('WebSocket message received:', data);
        switch (data.type) {
            case 'welcome':
                break;
            case 'new-message':
            case 'receive-message':
                if (this.messageHandler) {
                    this.messageHandler.handleIncomingMessage(data);
                }
                break;
            case 'user-joined':
                this.handleUserJoined(data.user);
                break;
            case 'user-left':
                this.handleUserLeft(data.user);
                break;
            case 'users-list':
                this.handleUsersList(data.users);
                break;
            default:
                console.log('Unknown message type:', data);
        }
    }

    handleUserJoined(user) {
        if (!this.connectedUsers.find(u => u.id === user.id)) {
            this.connectedUsers.push(user);
            console.log('User joined:', user);
            if (this.userHandler) {
                this.userHandler.updateChatsFromConnectedUsers(this.connectedUsers);
            }
        }
    }

    handleUserLeft(user) {
        this.connectedUsers = this.connectedUsers.filter(u => u.id !== user.id);
        console.log('User left:', user);
        if (this.userHandler) {
            this.userHandler.updateChatsFromConnectedUsers(this.connectedUsers);
            this.userHandler.handleUserLeft(user);
        }
    }

    handleUsersList(users) {
        this.connectedUsers = users;
        console.log('Users list updated:', users);
        if (this.userHandler) {
            this.userHandler.updateChatsFromConnectedUsers(this.connectedUsers);
        }
    }

    send(data) {
        if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
            this.websocket.send(JSON.stringify(data));
            return true;
        }
        return false;
    }

    isConnected() {
        return this.websocket && this.websocket.readyState === WebSocket.OPEN;
    }
}