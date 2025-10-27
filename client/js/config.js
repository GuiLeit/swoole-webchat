class Config {
    constructor() {
        const isSecure = window.location.protocol === 'https:';
        const wsProto = isSecure ? 'wss' : 'ws';

        this.websocketUrl = `${wsProto}://${window.location.host}/ws`;
        this.reconnectInterval = 5000;
        this.maxReconnectAttempts = 5;
    }
}

const config = new Config();