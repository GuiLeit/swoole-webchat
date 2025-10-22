class Config {
    constructor() {
        this.websocketUrl = 'ws://localhost:9501';
        this.reconnectInterval = 5000;
        this.maxReconnectAttempts = 5;
    }
}

const config = new Config();