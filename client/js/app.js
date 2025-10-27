class WhatsAppClone {
    constructor() {
        this.config = config;
        this.uiManager = null;
        this.chatManager = null;
        this.messageManager = null;
        this.websocketManager = null;
        this.toastManager = null;
    }

    initialize() {
        this.uiManager = new UIManager(() => this.chatManager);
        this.websocketManager = new WebSocketManager(this.config);
        this.chatManager = new ChatManager(this.uiManager, this.websocketManager);
        this.messageManager = new MessageManager(this.chatManager, this.uiManager, this.websocketManager);
        this.toastManager = new ToastManager();

        this.websocketManager.setMessageHandler(this.messageManager);
        this.websocketManager.setUserHandler(this.chatManager);
        this.websocketManager.setToastHandler(this.toastManager);
        
        this.messageManager.setupMessageInput();
        this.chatManager.clearChatArea();
        this.websocketManager.connect();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const app = new WhatsAppClone();
    app.initialize();
});