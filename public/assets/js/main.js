// Main Chat Application - public/assets/js/main.js
// Initializes the WebSocket client and handles the main chat functionality

let chatClient = null;
let currentRoomId = null;

// Initialize the chat application
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing chat application...');
    
    // Check if we have the required configuration
    if (!window.chatConfig || !window.chatConfig.wsUrl) {
        console.error('Chat configuration not found!');
        showError('Chat configuration not found. Please refresh the page.');
        return;
    }
    
    // Initialize WebSocket client
    initializeWebSocketClient();
    
    // Initialize UI event handlers
    initializeUIHandlers();
    
    // Initialize room list
    initializeRoomList();
    
    console.log('Chat application initialized successfully');
});

function initializeWebSocketClient() {
    const config = window.chatConfig;
    
    // Create WebSocket client instance
    chatClient = new ChatWebSocketClient(
        config.wsUrl,
        config.currentUser.id,
        config.currentUser.session_token,
        {
            reconnectAttempts: 5,
            reconnectDelay: 2000,
            pingInterval: 30000
        }
    );
    
    // Set up event handlers
    chatClient.on('onOpen', handleWebSocketOpen);
    chatClient.on('onClose', handleWebSocketClose);
    chatClient.on('onError', handleWebSocketError);
    chatClient.on('onAuthenticated', handleAuthenticated);
    chatClient.on('onRoomJoined', handleRoomJoined);
    chatClient.on('onRoomLeft', handleRoomLeft);
    chatClient.on('onNewMessage', handleNewMessage);
    chatClient.on('onUserJoined', handleUserJoined);
    chatClient.on('onUserLeft', handleUserLeft);
    chatClient.on('onUserTyping', handleUserTyping);
    chatClient.on('onUserStoppedTyping', handleUserStoppedTyping);
    chatClient.on('onUserStatusUpdate', handleUserStatusUpdate);
    chatClient.on('onConnectionStatusChange', handleConnectionStatusChange);
}

function initializeUIHandlers() {
    // Message form submission
    const messageForm = document.getElementById('messageForm');
    if (messageForm) {
        messageForm.addEventListener('submit', handleMessageSubmit);
    }
    
    // Create room form
    const createRoomForm = document.getElementById('createRoomForm');
    if (createRoomForm) {
        createRoomForm.addEventListener('submit', handleCreateRoom);
    }
    
    // Message input events
    const messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.addEventListener('input', handleTyping);
        messageInput.addEventListener('keydown', handleKeyDown);
    }
}

function initializeRoomList() {
    const config = window.chatConfig;
    if (config.rooms) {
        updateRoomList(config.rooms);
    }
}

// WebSocket Event Handlers
function handleWebSocketOpen() {
    console.log('WebSocket connection opened');
    updateConnectionStatus('connected');
    hideNotification();
}

function handleWebSocketClose() {
    console.log('WebSocket connection closed');
    updateConnectionStatus('disconnected');
    showNotification('Connection lost. Attempting to reconnect...', 'warning');
}

function handleWebSocketError(error) {
    console.error('WebSocket error:', error);
    updateConnectionStatus('error');
    showNotification('Connection error. Please check your internet connection.', 'danger');
}

function handleAuthenticated() {
    console.log('Successfully authenticated with WebSocket server');
    updateConnectionStatus('authenticated');
}

function handleRoomJoined(roomData) {
    console.log('Joined room:', roomData);
    currentRoomId = roomData.room_id;
    updateRoomUI(roomData);
    showNotification(`Joined room: ${roomData.room_name}`, 'success');
}

function handleRoomLeft(roomData) {
    console.log('Left room:', roomData);
    if (currentRoomId === roomData.room_id) {
        currentRoomId = null;
        clearRoomUI();
    }
    showNotification(`Left room: ${roomData.room_name}`, 'info');
}

function handleNewMessage(messageData) {
    console.log('New message received:', messageData);
    addMessageToUI(messageData);
}

function handleUserJoined(userData) {
    console.log('User joined:', userData);
    addUserToUI(userData);
    updateOnlineCount();
}

function handleUserLeft(userData) {
    console.log('User left:', userData);
    removeUserFromUI(userData);
    updateOnlineCount();
}

function handleUserTyping(userData) {
    console.log('User typing:', userData);
    showTypingIndicator(userData.username);
}

function handleUserStoppedTyping(userData) {
    console.log('User stopped typing:', userData);
    hideTypingIndicator(userData.username);
}

function handleUserStatusUpdate(userData) {
    console.log('User status update:', userData);
    updateUserStatusInUI(userData);
}

function handleConnectionStatusChange(status) {
    console.log('Connection status changed:', status);
    updateConnectionStatus(status);
}

// UI Event Handlers
function handleMessageSubmit(event) {
    event.preventDefault();
    
    const messageInput = document.getElementById('messageInput');
    const message = messageInput.value.trim();
    
    if (!message) return;
    
    if (chatClient && chatClient.isConnected()) {
        chatClient.sendMessage(message);
        messageInput.value = '';
    } else {
        showNotification('Not connected to chat server', 'warning');
    }
}

function handleCreateRoom(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const roomData = {
        name: formData.get('name'),
        description: formData.get('description'),
        type: formData.get('type')
    };
    
    // Send create room request
    fetch('/chat/create-room', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(roomData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Room created successfully!', 'success');
            // Close modal and refresh room list
            const modal = bootstrap.Modal.getInstance(document.getElementById('createRoomModal'));
            modal.hide();
            event.target.reset();
            // You might want to refresh the room list here
        } else {
            showNotification(data.error || 'Failed to create room', 'danger');
        }
    })
    .catch(error => {
        console.error('Error creating room:', error);
        showNotification('Failed to create room', 'danger');
    });
}

function handleTyping() {
    if (chatClient && chatClient.isConnected() && currentRoomId) {
        chatClient.startTyping();
    }
}

function handleKeyDown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        handleMessageSubmit(event);
    }
}

// UI Helper Functions
function updateConnectionStatus(status) {
    const statusElement = document.getElementById('connectionStatus');
    if (!statusElement) return;
    
    const statusMap = {
        'connecting': '<i class="fas fa-circle text-warning"></i> Connecting...',
        'connected': '<i class="fas fa-circle text-success"></i> Connected',
        'authenticated': '<i class="fas fa-circle text-success"></i> Connected',
        'disconnected': '<i class="fas fa-circle text-danger"></i> Disconnected',
        'error': '<i class="fas fa-circle text-danger"></i> Error'
    };
    
    statusElement.innerHTML = statusMap[status] || statusMap['disconnected'];
}

function showNotification(message, type = 'info') {
    const notification = document.getElementById('connectionNotification');
    const messageElement = document.getElementById('connectionMessage');
    
    if (notification && messageElement) {
        messageElement.textContent = message;
        notification.className = `notification alert alert-${type}`;
        notification.style.display = 'block';
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            hideNotification();
        }, 5000);
    }
}

function hideNotification() {
    const notification = document.getElementById('connectionNotification');
    if (notification) {
        notification.style.display = 'none';
    }
}

function showError(message) {
    showNotification(message, 'danger');
}

function updateRoomUI(roomData) {
    // Show room header
    const roomHeader = document.getElementById('roomHeader');
    const currentRoomName = document.getElementById('currentRoomName');
    const currentRoomDescription = document.getElementById('currentRoomDescription');
    
    if (roomHeader && currentRoomName && currentRoomDescription) {
        currentRoomName.textContent = roomData.room_name;
        currentRoomDescription.textContent = roomData.room_description || '';
        roomHeader.style.display = 'block';
    }
    
    // Show message input
    const messageInputArea = document.getElementById('messageInputArea');
    if (messageInputArea) {
        messageInputArea.style.display = 'block';
    }
    
    // Clear messages container
    const messagesContainer = document.getElementById('messagesContainer');
    if (messagesContainer) {
        messagesContainer.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin"></i> Loading messages...</div>';
    }
}

function clearRoomUI() {
    // Hide room header
    const roomHeader = document.getElementById('roomHeader');
    if (roomHeader) {
        roomHeader.style.display = 'none';
    }
    
    // Hide message input
    const messageInputArea = document.getElementById('messageInputArea');
    if (messageInputArea) {
        messageInputArea.style.display = 'none';
    }
    
    // Show welcome message
    const messagesContainer = document.getElementById('messagesContainer');
    if (messagesContainer) {
        messagesContainer.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="fas fa-comments fa-3x mb-3"></i>
                <h5>Welcome to Live Chat!</h5>
                <p>Select a room to start chatting with others.</p>
            </div>
        `;
    }
}

function addMessageToUI(messageData) {
    const messagesContainer = document.getElementById('messagesContainer');
    if (!messagesContainer) return;
    
    // Remove loading message if present
    const loadingMessage = messagesContainer.querySelector('.text-center');
    if (loadingMessage) {
        loadingMessage.remove();
    }
    
    const messageElement = createMessageElement(messageData);
    messagesContainer.appendChild(messageElement);
    
    // Scroll to bottom
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

function createMessageElement(messageData) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'message';
    
    const isOwnMessage = messageData.user_id == window.chatConfig.currentUser.id;
    const messageClass = isOwnMessage ? 'message-own' : 'message-other';
    
    messageDiv.innerHTML = `
        <div class="message ${messageClass}">
            <div class="message-header">
                <span class="message-username">${messageData.username}</span>
                <span class="message-time">${formatTime(messageData.timestamp)}</span>
            </div>
            <div class="message-content">
                ${escapeHtml(messageData.message)}
            </div>
        </div>
    `;
    
    return messageDiv;
}

function updateRoomList(rooms) {
    const roomList = document.getElementById('roomList');
    if (!roomList) return;
    
    roomList.innerHTML = '';
    
    rooms.forEach(room => {
        const roomElement = document.createElement('div');
        roomElement.className = 'room-item';
        roomElement.setAttribute('data-room-id', room.id);
        roomElement.onclick = () => joinRoom(room.id);
        
        roomElement.innerHTML = `
            <div class="d-flex justify-content-between">
                <div>
                    <i class="fas ${room.type === 'private' ? 'fa-lock' : 'fa-globe'}"></i>
                    <strong>${escapeHtml(room.name)}</strong>
                </div>
                <small class="text-muted">${room.type}</small>
            </div>
            ${room.description ? `<small class="text-muted d-block mt-1">${escapeHtml(room.description)}</small>` : ''}
        `;
        
        roomList.appendChild(roomElement);
    });
}

function addUserToUI(userData) {
    const onlineUsers = document.getElementById('onlineUsers');
    if (!onlineUsers) return;
    
    const userElement = document.createElement('div');
    userElement.className = 'user-item';
    userElement.setAttribute('data-user-id', userData.id);
    
    userElement.innerHTML = `
        <div class="d-flex align-items-center">
            <div class="avatar" style="width: 20px; height: 20px; font-size: 0.7rem; margin-right: 8px;">
                ${userData.username.charAt(0).toUpperCase()}
            </div>
            <span class="username">${escapeHtml(userData.username)}</span>
            <span class="status-indicator ${userData.status}"></span>
        </div>
    `;
    
    onlineUsers.appendChild(userElement);
}

function removeUserFromUI(userData) {
    const userElement = document.querySelector(`[data-user-id="${userData.id}"]`);
    if (userElement) {
        userElement.remove();
    }
}

function updateUserStatusInUI(userData) {
    const userElement = document.querySelector(`[data-user-id="${userData.id}"]`);
    if (userElement) {
        const statusIndicator = userElement.querySelector('.status-indicator');
        if (statusIndicator) {
            statusIndicator.className = `status-indicator ${userData.status}`;
        }
    }
}

function updateOnlineCount() {
    const onlineCount = document.getElementById('onlineCount');
    const onlineUsers = document.getElementById('onlineUsers');
    
    if (onlineCount && onlineUsers) {
        const count = onlineUsers.children.length;
        onlineCount.textContent = count;
    }
}

function showTypingIndicator(username) {
    const typingIndicator = document.getElementById('typingIndicator');
    if (typingIndicator) {
        typingIndicator.innerHTML = `<small class="text-muted"><i>${escapeHtml(username)} is typing...</i></small>`;
        typingIndicator.style.display = 'block';
    }
}

function hideTypingIndicator(username) {
    const typingIndicator = document.getElementById('typingIndicator');
    if (typingIndicator) {
        typingIndicator.style.display = 'none';
    }
}

// Global Functions (called from HTML)
function joinRoom(roomId) {
    if (chatClient && chatClient.isConnected()) {
        chatClient.joinRoom(roomId);
    } else {
        showNotification('Not connected to chat server', 'warning');
    }
}

function leaveCurrentRoom() {
    if (chatClient && chatClient.isConnected() && currentRoomId) {
        chatClient.leaveRoom();
    }
}

function updateStatus(status) {
    // Send status update to server
    fetch('/chat/update-status', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ status: status })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Status updated successfully', 'success');
        }
    })
    .catch(error => {
        console.error('Error updating status:', error);
    });
}

// Utility Functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatTime(timestamp) {
    const date = new Date(timestamp);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
} 