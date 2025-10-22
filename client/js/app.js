class WhatsAppClone {
    constructor() {
        this.config = config;
        this.uiManager = null;
        this.chatManager = null;
        this.messageManager = null;
        this.websocketManager = null;
    }

    initialize() {
        this.uiManager = new UIManager(() => this.chatManager);
        this.chatManager = new ChatManager(this.uiManager);
        this.websocketManager = new WebSocketManager(this.config);
        this.messageManager = new MessageManager(this.chatManager, this.uiManager, this.websocketManager);
        
        this.websocketManager.setMessageHandler(this.messageManager);
        this.websocketManager.setUserHandler(this.chatManager);
        
        this.messageManager.setupMessageInput();
        this.chatManager.clearChatArea();
        this.websocketManager.connect();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const app = new WhatsAppClone();
    app.initialize();
});