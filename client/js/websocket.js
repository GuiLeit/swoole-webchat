class WebSocketManager {
    constructor(config) {
        this.config = config;
        this.websocket = null;
        this.connectedUsers = [];
        this.messageHandler = null;
        this.userHandler = null;
        this.toastHandler = null;
        this.authenticated = false;
        this.userId = null;
        this.userToken = null;
    }

    setMessageHandler(handler) {
        this.messageHandler = handler;
    }

    setUserHandler(handler) {
        this.userHandler = handler;
    }

    setToastHandler(handler) {
        this.toastHandler = handler;
    }

    connect() {
        try {
            this.websocket = new WebSocket(this.config.websocketUrl);

            this.websocket.onopen = (event) => {
                console.log('WebSocket connected');
                // Send authentication immediately after connection
                if (typeof this.authenticate === 'function') {
                    this.authenticate();
                } else {
                    console.warn('No authenticate() method defined on WebSocketManager');
                }
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
                this.authenticated = false;
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

    authenticate() {
        if (!window.currentUser) {
            console.error('No user data found for authentication');
            return;
        }

        const authData = {
            action: 'auth',
            data: {
                username: window.currentUser.username,
                avatar_url: window.currentUser.avatarUrl,
                token: window.userToken || null
            }
        };

        this.send(authData);
    }

    handleMessage(data) {
        console.log('WebSocket message received:', data);
        switch (data.type) {
            case 'auth-ok':
                this.handleAuthSuccess(data);
                break;
            case 'auth-error':
                this.handleAuthError(data);
                break;
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
            case 'error':
                this.handleError(data.message);
                break;
            default:
                console.log('Unknown message type:', data);
        }
    }

    handleAuthSuccess(data) {
        console.log('Authentication successful:', data);
        this.authenticated = true;
        this.userId = data.user_id;
        this.userToken = data.token;

        // Store token for future connections
        if (data.token) {
            localStorage.setItem('userToken', data.token);
        }

        // Update current user with server-assigned ID
        if (window.currentUser) {
            window.currentUser.id = data.user_id;
        }
    }

    handleAuthError(data) {
        console.error('Authentication failed:', data.message);
        // Clear stored data and redirect to auth page
        localStorage.removeItem('userData');
        localStorage.removeItem('userToken');
        window.location.href = '/auth.html';
    }

    handleError(message) {
        console.error('WebSocket error message:', message);
        if (this.toastHandler) {
            this.toastHandler.error(message, 'WebSocket Error', 5000);
        }
    }

    handleUserJoined(user) {
        if (!this.connectedUsers.find(u => u.id === user.id)) {
            this.connectedUsers.push(user);
            this.toastHandler.success(
                `${user.username} joined the chat`,
                'User Joined',
                4000
            );
            console.log('User joined:', user);
            if (this.userHandler) {
                this.userHandler.updateChatsFromConnectedUsers(this.connectedUsers);
            }
        }
    }

    handleUserLeft(user) {
        this.connectedUsers = this.connectedUsers.filter(u => u.id !== user.id);
        this.toastHandler.info(
            `${user.username} left the chat`,
            'User Left',
            4000
        );
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