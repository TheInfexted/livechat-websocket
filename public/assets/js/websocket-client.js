// WebSocket Client - public/assets/js/websocket-client.js
// Advanced WebSocket Client for Live Chat with full functionality

class ChatWebSocketClient {
    constructor(wsUrl, userId, sessionToken, options = {}) {
        this.wsUrl = wsUrl;
        this.userId = userId;
        this.sessionToken = sessionToken;
        this.options = {
            reconnectAttempts: 5,
            reconnectDelay: 1000,
            pingInterval: 30000,
            maxReconnectDelay: 30000,
            heartbeatInterval: 60000,
            ...options
        };
        
        // Connection state
        this.ws = null;
        this.currentRoomId = null;
        this.reconnectAttempts = 0;
        this.isConnecting = false;
        this.isAuthenticated = false;
        this.lastPingTime = null;
        this.connectionId = null;
        
        // Intervals
        this.pingInterval = null;
        this.heartbeatInterval = null;
        this.reconnectTimeout = null;
        
        // Message queue for offline messages
        this.messageQueue = [];
        this.maxQueueSize = 100;
        
        // Statistics
        this.stats = {
            messagesReceived: 0,
            messagesSent: 0,
            reconnectCount: 0,
            connectionUptime: 0,
            startTime: Date.now()
        };
        
        // Event callbacks
        this.callbacks = {
            onOpen: null,
            onClose: null,
            onError: null,
            onMessage: null,
            onAuthenticated: null,
            onRoomJoined: null,
            onRoomLeft: null,
            onNewMessage: null,
            onUserJoined: null,
            onUserLeft: null,
            onUserLeftRoom: null,
            onUserTyping: null,
            onUserStoppedTyping: null,
            onUserStatusUpdate: null,
            onUserConnected: null,
            onUserDisconnected: null,
            onOnlineUsersList: null,
            onConnectionStatusChange: null,
            onReconnecting: null,
            onReconnected: null
        };
        
        // Auto-connect
        this.connect();
    }

    // Connection Management
    connect() {
        if (this.isConnecting) {
            console.log('Already connecting, skipping...');
            return;
        }
        
        this.isConnecting = true;
        this.triggerCallback('onConnectionStatusChange', 'connecting');
        
        try {

            this.ws = new WebSocket(this.wsUrl);
            
            this.ws.onopen = (event) => this.handleOpen(event);
            this.ws.onmessage = (event) => this.handleMessage(event);
            this.ws.onclose = (event) => this.handleClose(event);
            this.ws.onerror = (error) => this.handleError(error);
            
        } catch (error) {
            console.error('Failed to create WebSocket connection:', error);
            this.isConnecting = false;
            this.triggerCallback('onError', error);
            this.triggerCallback('onConnectionStatusChange', 'failed');
            this.attemptReconnect();
        }
    }

    disconnect(code = 1000, reason = 'Client disconnecting') {
        console.log('Disconnecting WebSocket...');
        this.cleanup();
        
        if (this.ws) {
            this.ws.close(code, reason);
            this.ws = null;
        }
        
        this.isAuthenticated = false;
        this.currentRoomId = null;
        this.triggerCallback('onConnectionStatusChange', 'disconnected');
    }

    reconnect() {
        console.log('Manual reconnect requested');
        this.disconnect();
        this.reconnectAttempts = 0;
        setTimeout(() => this.connect(), 1000);
    }

    // Event Handlers
    handleOpen(event) {
        
        this.isConnecting = false;
        this.reconnectAttempts = 0;
        this.stats.connectionUptime = Date.now();
        
        this.startHeartbeat();
        
        this.triggerCallback('onOpen', event);
        this.triggerCallback('onConnectionStatusChange', 'connected');
    }

    handleMessage(event) {
        try {
            const data = JSON.parse(event.data);
            this.stats.messagesReceived++;
            this.lastPingTime = Date.now();
            
    
            
            this.processMessage(data);
            this.triggerCallback('onMessage', data);
        } catch (error) {
            console.error('Error parsing WebSocket message:', error, event.data);
        }
    }

    handleClose(event) {

        this.isConnecting = false;
        this.isAuthenticated = false;
        this.cleanup();
        
        this.triggerCallback('onClose', event);
        this.triggerCallback('onConnectionStatusChange', 'disconnected');
        
        // Auto-reconnect unless it was a clean close
        if (event.code !== 1000) {
            this.attemptReconnect();
        }
    }

    handleError(error) {
        console.error('WebSocket error:', error);
        this.isConnecting = false;
        this.triggerCallback('onError', error);
        this.triggerCallback('onConnectionStatusChange', 'error');
    }

    // Message Processing
    processMessage(data) {
        switch (data.type) {
            case 'connection_established':
                this.connectionId = data.connection_id;
        
                // Send authentication after connection is established
                this.authenticate();
                break;
                
            case 'authenticated':
                this.isAuthenticated = true;
                this.processMessageQueue();
                this.triggerCallback('onAuthenticated', data.user);
                if (this.reconnectAttempts > 0) {
                    this.triggerCallback('onReconnected', data.user);
                }
                break;
                
            case 'room_joined':
                this.currentRoomId = data.room.id;
                this.triggerCallback('onRoomJoined', data.room, data.messages, data.participants);
                break;
                
            case 'room_left':
                this.currentRoomId = null;
                this.triggerCallback('onRoomLeft', data);
                break;
                
            case 'new_message':
                this.triggerCallback('onNewMessage', data.message);
                break;
                
            case 'user_left_room':
                this.triggerCallback('onUserLeftRoom', data);
                break;
                
            case 'user_joined_room':
                this.triggerCallback('onUserJoined', data.user, data.room_id);
                break;
                
            case 'user_status_update':
                this.triggerCallback('onUserStatusUpdate', data);
                break;
                
            case 'user_connected':
                this.triggerCallback('onUserConnected', data);
                break;
                
            case 'user_disconnected':
                this.triggerCallback('onUserDisconnected', data);
                break;
                
            case 'online_users_list':
                this.triggerCallback('onOnlineUsersList', data.users);
                break;
                
            case 'user_typing_start':
                this.triggerCallback('onUserTyping', data.user, data.room_id);
                break;
                
            case 'user_typing_stop':
                this.triggerCallback('onUserStoppedTyping', data.user, data.room_id);
                break;
                
            case 'user_status_update':
                this.triggerCallback('onUserStatusUpdate', data.user_id, data.status);
                break;
                
            case 'server_shutdown':
        
                this.disconnect(1001, 'Server shutdown');
                break;
                
            case 'error':
                console.error('Server error:', data.message);
                this.triggerCallback('onError', new Error(data.message));
                break;
                
            case 'pong':
                // Heartbeat response received
                break;
                
            default:
                console.warn('Unknown message type:', data.type);
        }
    }

    // Message Sending
    send(data) {

        
        // Allow authentication messages even when not authenticated
        const canSend = this.isConnected() && (this.isAuthenticated || data.type === 'authenticate');
        
        if (canSend) {
            try {
                this.ws.send(JSON.stringify(data));
                this.stats.messagesSent++;
        
                return true;
            } catch (error) {
                console.error('Error sending WebSocket message:', error);
                this.queueMessage(data);
                return false;
            }
        } else {
    
            this.queueMessage(data);
            return false;
        }
    }

    queueMessage(data) {
        if (this.messageQueue.length >= this.maxQueueSize) {
            this.messageQueue.shift(); // Remove oldest message
        }
        this.messageQueue.push(data);

    }

    processMessageQueue() {

        while (this.messageQueue.length > 0) {
            const data = this.messageQueue.shift();
            this.send(data);
        }
    }

    // Chat Actions
    authenticate() {
        return this.send({
            type: 'authenticate',
            user_id: this.userId,
            session_token: this.sessionToken
        });
    }

    joinRoom(roomId) {
        this.currentRoomId = roomId;
        return this.send({
            type: 'join_room',
            room_id: roomId
        });
    }

    leaveRoom() {
        if (this.currentRoomId) {
            const roomId = this.currentRoomId;
            this.currentRoomId = null;
            return this.send({
                type: 'leave_room',
                room_id: roomId
            });
        }
        return false;
    }

    sendMessage(message) {
        if (!this.currentRoomId) {
            console.warn('Cannot send message: not in a room');
            return false;
        }
        
        return this.send({
            type: 'send_message',
            message: message,
            room_id: this.currentRoomId
        });
    }

    startTyping() {
        if (this.currentRoomId) {
            return this.send({
                type: 'typing_start',
                room_id: this.currentRoomId
            });
        }
        return false;
    }

    stopTyping() {
        if (this.currentRoomId) {
            return this.send({
                type: 'typing_stop',
                room_id: this.currentRoomId
            });
        }
        return false;
    }

    // Connection Utilities
    isConnected() {
        return this.ws && this.ws.readyState === WebSocket.OPEN;
    }

    ping() {
        if (this.isConnected()) {
            this.send({ type: 'ping' });
            return true;
        }
        return false;
    }

    startHeartbeat() {
        this.cleanup();
        
        // Regular ping
        this.pingInterval = setInterval(() => {
            if (this.isConnected()) {
                this.ping();
            }
        }, this.options.pingInterval);
        
        // Connection health check
        this.heartbeatInterval = setInterval(() => {
            const now = Date.now();
            if (this.lastPingTime && (now - this.lastPingTime) > this.options.heartbeatInterval) {
                console.warn('Connection appears to be stale, reconnecting...');
                this.reconnect();
            }
        }, this.options.heartbeatInterval);
    }

    cleanup() {
        if (this.pingInterval) {
            clearInterval(this.pingInterval);
            this.pingInterval = null;
        }
        
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
            this.heartbeatInterval = null;
        }
        
        if (this.reconnectTimeout) {
            clearTimeout(this.reconnectTimeout);
            this.reconnectTimeout = null;
        }
    }

    attemptReconnect() {
        if (this.reconnectAttempts >= this.options.reconnectAttempts) {
            console.error('Max reconnection attempts reached');
            this.triggerCallback('onConnectionStatusChange', 'failed');
            return;
        }

        this.reconnectAttempts++;
        this.stats.reconnectCount++;
        
        const delay = Math.min(
            this.options.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1),
            this.options.maxReconnectDelay
        );
        

        this.triggerCallback('onReconnecting', this.reconnectAttempts, delay);
        this.triggerCallback('onConnectionStatusChange', 'reconnecting');
        
        this.reconnectTimeout = setTimeout(() => {
            this.connect();
        }, delay);
    }

    // Event Management
    on(eventName, callback) {
        const callbackName = `on${eventName.charAt(0).toUpperCase()}${eventName.slice(1)}`;
        if (callbackName in this.callbacks) {
            this.callbacks[callbackName] = callback;
        } else {
            console.warn(`Unknown event: ${eventName}`);
        }
        return this;
    }

    off(eventName) {
        const callbackName = `on${eventName.charAt(0).toUpperCase()}${eventName.slice(1)}`;
        if (callbackName in this.callbacks) {
            this.callbacks[callbackName] = null;
        }
        return this;
    }

    triggerCallback(callbackName, ...args) {
        if (typeof this.callbacks[callbackName] === 'function') {
            try {
                this.callbacks[callbackName](...args);
            } catch (error) {
                console.error(`Error in callback ${callbackName}:`, error);
            }
        }
    }

    // Utility Methods
    getConnectionState() {
        if (!this.ws) return 'not_initialized';
        
        switch (this.ws.readyState) {
            case WebSocket.CONNECTING: return 'connecting';
            case WebSocket.OPEN: return 'open';
            case WebSocket.CLOSING: return 'closing';
            case WebSocket.CLOSED: return 'closed';
            default: return 'unknown';
        }
    }

    getStats() {
        const now = Date.now();
        return {
            ...this.stats,
            connectionState: this.getConnectionState(),
            isAuthenticated: this.isAuthenticated,
            currentRoomId: this.currentRoomId,
            reconnectAttempts: this.reconnectAttempts,
            messageQueueLength: this.messageQueue.length,
            isConnecting: this.isConnecting,
            uptime: this.stats.connectionUptime ? now - this.stats.connectionUptime : 0,
            totalUptime: now - this.stats.startTime,
            lastPing: this.lastPingTime ? now - this.lastPingTime : null
        };
    }

    // Debug Methods
    debugInfo() {
        return {
            url: this.wsUrl,
            state: this.getConnectionState(),
            stats: this.getStats(),
            options: this.options
        };
    }
}

// Export for use in other files
if (typeof window !== 'undefined') {
    window.ChatWebSocketClient = ChatWebSocketClient;
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = ChatWebSocketClient;
}