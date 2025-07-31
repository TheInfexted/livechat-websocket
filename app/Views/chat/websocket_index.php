<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Chat - WebSocket</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/chat.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid chat-container">
        <!-- Connection Status Notification -->
        <div id="connectionNotification" class="notification" style="display: none;">
            <div class="alert alert-info">
                <div class="d-flex align-items-center">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                    <span id="connectionMessage">Connecting to chat server...</span>
                </div>
            </div>
        </div>

        <div class="row h-100">
            <!-- Sidebar -->
            <div class="col-md-3 sidebar p-0">
                <div class="p-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Live Chat</h5>
                        <div class="connection-status" id="connectionStatus">
                            <i class="fas fa-circle"></i> Connecting...
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <div class="avatar" style="width: 20px; height: 20px; font-size: 0.7rem; margin-right: 4px;">
                                    <?= strtoupper(substr($currentUser['username'], 0, 1)) ?>
                                </div>
                                <?= $currentUser['username'] ?>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="/logout">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Rooms Section -->
                <div class="p-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">
                            <i class="fas fa-comments"></i> Rooms
                        </h6>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createRoomModal">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="room-list" id="roomList">
                        <?php foreach ($rooms as $room): ?>
                            <div class="room-item" data-room-id="<?= $room['id'] ?>" onclick="joinRoom(<?= $room['id'] ?>)">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <i class="fas fa-globe"></i>
                                        <strong><?= esc($room['name']) ?></strong>
                                    </div>
                                    <small class="text-muted">public</small>
                                </div>
                                <?php if (!empty($room['description'])): ?>
                                    <small class="text-muted d-block mt-1"><?= esc($room['description']) ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Online Users Section -->
                <div class="p-3">
                    <h6 class="mb-2">
                        <i class="fas fa-users"></i> Online Users 
                        <span class="badge bg-success" id="onlineCount">0</span>
                    </h6>
                    <div class="user-list" id="onlineUsers">
                        <!-- Users will be populated by JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Chat Area -->
            <div class="col-md-9 p-0">
                <div id="chatArea" class="chat-area">
                    <div class="room-header" id="roomHeader" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 id="currentRoomName" class="mb-0"></h5>
                                <small id="currentRoomDescription" class="text-muted"></small>
                            </div>
                                <div>
                                    <button class="btn btn-sm btn-outline-danger" onclick="leaveCurrentRoom()">
                                        <i class="fas fa-sign-out-alt"></i> Leave
                                    </button>
                                </div>
                        </div>
                    </div>

                    <div class="messages-container" id="messagesContainer">
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-comments fa-3x mb-3"></i>
                            <h5>Welcome to Live Chat!</h5>
                            <p>Select a room to start chatting with others.</p>
                        </div>
                    </div>

                    <div class="typing-indicator" id="typingIndicator"></div>

                    <div class="message-input" id="messageInputArea" style="display: none;">
                        <form id="messageForm">
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control" 
                                       id="messageInput" 
                                       placeholder="Type your message..." 
                                       maxlength="1000"
                                       autocomplete="off">
                                <button class="btn btn-primary" type="submit" id="sendButton">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Room Modal -->
    <div class="modal fade" id="createRoomModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle"></i> Create New Room
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="createRoomForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="roomName" class="form-label">Room Name</label>
                            <input type="text" class="form-control" id="roomName" name="name" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label for="roomDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="roomDescription" name="description" rows="3" maxlength="500"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="roomType" class="form-label">Room Type</label>
                            <select class="form-select" id="roomType" name="type">
                                <option value="public">Public - Anyone can join</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Room
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/websocket-client.js"></script>
    <script src="/assets/js/chat.js"></script>
    <script>
        // Pass PHP data to JavaScript
        window.chatConfig = {
            currentUser: <?= json_encode($currentUser) ?>,
            wsUrl: '<?= $wsUrl ?>',
            rooms: <?= json_encode($rooms) ?>,
            onlineUsers: <?= json_encode($onlineUsers) ?>
        };
        
        // Initialize WebSocket client
        let chatClient = null;
        let currentRoomId = null;
        
        document.addEventListener('DOMContentLoaded', function() {

            
            if (!window.chatConfig || !window.chatConfig.wsUrl) {
                console.error('Chat configuration not found!');
                return;
            }
            
            // Create WebSocket client instance
            chatClient = new ChatWebSocketClient(
                window.chatConfig.wsUrl,
                window.chatConfig.currentUser.id,
                window.chatConfig.currentUser.session_token,
                {
                    reconnectAttempts: 5,
                    reconnectDelay: 2000,
                    pingInterval: 30000
                }
            );
            
            // Set up event handlers
            chatClient.on('Open', function() {
                updateConnectionStatus('connected');
            });
            
            chatClient.on('Close', function() {
                updateConnectionStatus('disconnected');
            });
            
            chatClient.on('Error', function(error) {
                console.error('WebSocket error:', error);
                updateConnectionStatus('error');
            });
            
            chatClient.on('Authenticated', function() {

                updateConnectionStatus('authenticated');
            });
            
            chatClient.on('RoomJoined', function(room, messages, participants) {
                // The WebSocket client passes room, messages, and participants as separate parameters
                if (room) {
                    currentRoomId = room.id;
                    updateRoomUI(room);
                    
                    // Show success notification
                    showNotification(`Successfully joined room: ${room.name}`, 'success');
                    
                    // Update room list to show active room
                    updateActiveRoomInList(room.id);
                    
                    // Display existing messages
                    if (messages && messages.length > 0) {
                        messages.forEach(message => {
                            addMessageToUI(message);
                        });
                    }
                } else {
                    console.error('No room data received in RoomJoined event');
                }
            });
            
            chatClient.on('NewMessage', function(messageData) {
                // The WebSocket client now passes the message data directly
                if (messageData && typeof messageData === 'object') {
                    addMessageToUI(messageData);
                } else {
                    console.error('Invalid message data received:', messageData);
                }
            });
            
            chatClient.on('UserLeftRoom', function(data) {
                // Handle when a user leaves the room
                if (data && data.user) {
                    showNotification(`${data.user.username} left the room`, 'info');
                }
            });
            
            chatClient.on('UserJoined', function(user, roomId) {
                // Handle when a user joins the room
                if (user && user.username) {
                    showNotification(`${user.username} joined the room`, 'success');
                }
            });
            
            // Initialize online users list
            updateOnlineUsersList();
            
            // Handle user status updates
            chatClient.on('UserStatusUpdate', function(data) {
                if (data && data.user_id && data.status) {
                    updateUserStatus(data.user_id, data.status);
                }
            });
            
            // Handle user connections
            chatClient.on('UserConnected', function(data) {
                if (data && data.user) {
                    addUserToList(data.user);
                    showNotification(`${data.user.username} is now online`, 'success');
                }
            });
            
            // Handle user disconnections
            chatClient.on('UserDisconnected', function(data) {
                if (data && data.user) {
                    removeUserFromList(data.user.id);
                    showNotification(`${data.user.username} went offline`, 'info');
                }
            });
            
            // Handle online users list from server
            chatClient.on('OnlineUsersList', function(users) {
                if (users && Array.isArray(users)) {
                    // Clear current list
                    const onlineUsersContainer = document.getElementById('onlineUsers');
                    if (onlineUsersContainer) {
                        onlineUsersContainer.innerHTML = '';
                    }
                    
                    // Add each user
                    users.forEach(user => {
                        addUserToList(user);
                    });
                }
            });
            
            // Initialize message form
            const messageForm = document.getElementById('messageForm');
            if (messageForm) {
                messageForm.addEventListener('submit', function(event) {
                    event.preventDefault();
                    const messageInput = document.getElementById('messageInput');
                    const message = messageInput.value.trim();
                    
                    if (message && chatClient && chatClient.isConnected()) {
                        chatClient.sendMessage(message);
                        messageInput.value = '';
                    }
                });
            } else {
                console.error('Message form not found!');
            }
            
            // Initialize room creation form
            const createRoomForm = document.getElementById('createRoomForm');
            if (createRoomForm) {
                createRoomForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(createRoomForm);
                    const roomData = {
                        name: formData.get('name'),
                        description: formData.get('description'),
                        type: formData.get('type')
                    };
                    
                    // Send room creation request
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
                            // Add new room to the room list
                            addRoomToList(data.room);
                            
                            // Close modal
                            const modal = bootstrap.Modal.getInstance(document.getElementById('createRoomModal'));
                            if (modal) {
                                modal.hide();
                            }
                            
                            // Reset form
                            createRoomForm.reset();
                            
                            // Show success notification
                            showNotification('Room created successfully!', 'success');
                        } else {
                            showNotification('Failed to create room: ' + (data.error || 'Unknown error'), 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error creating room:', error);
                        showNotification('Error creating room', 'error');
                    });
                });
            }
            

        });
        
        // Global functions (called from HTML)
        function joinRoom(roomId) {
            if (chatClient && chatClient.isConnected()) {
                chatClient.joinRoom(roomId);
            }
        }
        
        function leaveCurrentRoom() {
            if (chatClient && chatClient.isConnected() && currentRoomId) {
                // Ask for confirmation
                if (confirm('Are you sure you want to leave this room?')) {
                    chatClient.leaveRoom();
                    
                    // Clear current room
                    currentRoomId = null;
                    
                    // Update UI to show no room selected
                    const roomHeader = document.getElementById('roomHeader');
                    const messageInputArea = document.getElementById('messageInputArea');
                    const messagesContainer = document.getElementById('messagesContainer');
                    
                    if (roomHeader) {
                        roomHeader.style.display = 'none';
                    }
                    
                    if (messageInputArea) {
                        messageInputArea.style.display = 'none';
                    }
                    
                    if (messagesContainer) {
                        messagesContainer.innerHTML = '<div class="text-center text-muted py-3">Select a room to start chatting</div>';
                    }
                    
                    // Remove active class from room list
                    const roomItems = document.querySelectorAll('.room-item');
                    roomItems.forEach(item => {
                        item.classList.remove('active');
                    });
                    
                    // Show notification
                    showNotification('Left the room', 'info');
                }
            }
        }
        
        function updateConnectionStatus(status) {
            const statusElement = document.getElementById('connectionStatus');
            if (statusElement) {
                const statusMap = {
                    'connecting': '<i class="fas fa-circle text-warning"></i> Connecting...',
                    'connected': '<i class="fas fa-circle text-success"></i> Connected',
                    'authenticated': '<i class="fas fa-circle text-success"></i> Connected',
                    'disconnected': '<i class="fas fa-circle text-danger"></i> Disconnected',
                    'error': '<i class="fas fa-circle text-danger"></i> Error'
                };
                statusElement.innerHTML = statusMap[status] || statusMap['disconnected'];
            }
        }
        
        function updateRoomUI(roomData) {
            const roomHeader = document.getElementById('roomHeader');
            const currentRoomName = document.getElementById('currentRoomName');
            const currentRoomDescription = document.getElementById('currentRoomDescription');
            const messageInputArea = document.getElementById('messageInputArea');
            const messagesContainer = document.getElementById('messagesContainer');
            
            if (roomHeader && currentRoomName && currentRoomDescription) {
                currentRoomName.textContent = roomData.name;
                currentRoomDescription.textContent = roomData.description || '';
                roomHeader.style.display = 'block';
            }
            
            if (messageInputArea) {
                messageInputArea.style.display = 'block';
            } else {
                console.error('Message input area not found!');
            }
            
            if (messagesContainer) {
                messagesContainer.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin"></i> Loading messages...</div>';
            } else {
                console.error('Messages container not found!');
            }
        }
        
        function addMessageToUI(messageData) {
            const messagesContainer = document.getElementById('messagesContainer');
            if (!messagesContainer) {
                console.error('Messages container not found!');
                return;
            }
            
            // Remove loading message if present
            const loadingMessage = messagesContainer.querySelector('.text-center');
            if (loadingMessage) {
                loadingMessage.remove();
            }
            
            // Check if messageData has required fields
            if (!messageData || typeof messageData !== 'object') {
                console.error('Invalid messageData:', messageData);
                return;
            }
            
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message';
            
            const isOwnMessage = messageData.user_id == window.chatConfig.currentUser.id;
            const messageClass = isOwnMessage ? 'message-own' : 'message-other';
            
            // Use fallback values if fields are missing
            const username = messageData.username || 'Unknown User';
            const timestamp = messageData.created_at ? new Date(messageData.created_at).toLocaleTimeString() : 'Unknown Time';
            const messageContent = messageData.message || 'No message content';
            
            messageDiv.innerHTML = `
                <div class="message ${messageClass}">
                    <div class="message-header">
                        <span class="message-username">${username}</span>
                        <span class="message-time">${timestamp}</span>
                    </div>
                    <div class="message-content">
                        ${messageContent}
                    </div>
                </div>
            `;
            
            messagesContainer.appendChild(messageDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        
        // Show notification function
        function showNotification(message, type = 'info') {
            const notification = document.getElementById('connectionNotification');
            const messageElement = document.getElementById('connectionMessage');
            
            if (notification && messageElement) {
                messageElement.textContent = message;
                notification.className = `notification alert alert-${type}`;
                notification.style.display = 'block';
                
                // Auto-hide after 3 seconds
                setTimeout(() => {
                    hideNotification();
                }, 3000);
            }
        }
        
        function hideNotification() {
            const notification = document.getElementById('connectionNotification');
            if (notification) {
                notification.style.display = 'none';
            }
        }
        
        // Update active room in room list
        function updateActiveRoomInList(roomId) {
            const roomItems = document.querySelectorAll('.room-item');
            roomItems.forEach(item => {
                item.classList.remove('active');
                if (item.getAttribute('data-room-id') == roomId) {
                    item.classList.add('active');
                }
            });
        }
        
        // Status update function
        function updateStatus(status) {
            if (chatClient && chatClient.isConnected()) {
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

                        // Update the status indicator in the UI
                        const statusElement = document.querySelector('.connection-status');
                        if (statusElement) {
                            const statusMap = {
                                'online': '<i class="fas fa-circle text-success"></i> Online',
                                'away': '<i class="fas fa-circle text-warning"></i> Away',
                                'offline': '<i class="fas fa-circle text-secondary"></i> Offline'
                            };
                            statusElement.innerHTML = statusMap[status] || statusMap['online'];
                        }
                    } else {
                        console.error('Failed to update status:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Error updating status:', error);
                });
            }
        }
        
        // Update online users list
        function updateOnlineUsersList() {
            const onlineUsersContainer = document.getElementById('onlineUsers');
            const onlineCountElement = document.getElementById('onlineCount');
            
            if (!onlineUsersContainer || !window.chatConfig.onlineUsers) {
                return;
            }
            
            const onlineUsers = window.chatConfig.onlineUsers;
            let html = '';
            
            if (onlineUsers.length > 0) {
                onlineUsers.forEach(user => {
                    const isCurrentUser = user.id == window.chatConfig.currentUser.id;
                    const userClass = isCurrentUser ? 'current-user' : '';
                    const statusClass = user.status === 'online' ? 'text-success' : 'text-muted';
                    
                    html += `
                        <div class="user-item ${userClass}" data-user-id="${user.id}">
                            <div class="d-flex align-items-center">
                                <div class="user-avatar me-2">
                                    <i class="fas fa-user-circle fa-lg ${statusClass}"></i>
                                </div>
                                <div class="user-info flex-grow-1">
                                    <div class="user-name">${user.username}</div>
                                    <small class="text-muted">${user.status}</small>
                                </div>
                            </div>
                        </div>
                    `;
                });
            } else {
                html = '<div class="text-muted small">No users online</div>';
            }
            
            onlineUsersContainer.innerHTML = html;
            
            if (onlineCountElement) {
                onlineCountElement.textContent = onlineUsers.length;
            }
        }
        
        // Update individual user status
        function updateUserStatus(userId, status) {
            const userItem = document.querySelector(`[data-user-id="${userId}"]`);
            if (userItem) {
                const statusElement = userItem.querySelector('.text-muted');
                if (statusElement) {
                    statusElement.textContent = status;
                }
                
                const avatarElement = userItem.querySelector('.fa-user-circle');
                if (avatarElement) {
                    avatarElement.className = `fas fa-user-circle fa-lg ${status === 'online' ? 'text-success' : 'text-muted'}`;
                }
            }
            
            // Refresh the online users list
            updateOnlineUsersList();
        }
        
        // Add user to online list
        function addUserToList(user) {
            const onlineUsersContainer = document.getElementById('onlineUsers');
            if (!onlineUsersContainer) return;
            
            // Check if user already exists
            const existingUser = document.querySelector(`[data-user-id="${user.id}"]`);
            if (existingUser) return;
            
            const isCurrentUser = user.id == window.chatConfig.currentUser.id;
            const userClass = isCurrentUser ? 'current-user' : '';
            const statusClass = user.status === 'online' ? 'text-success' : 'text-muted';
            
            const userHtml = `
                <div class="user-item ${userClass}" data-user-id="${user.id}">
                    <div class="d-flex align-items-center">
                        <div class="user-avatar me-2">
                            <i class="fas fa-user-circle fa-lg ${statusClass}"></i>
                        </div>
                        <div class="user-info flex-grow-1">
                            <div class="user-name">${user.username}</div>
                            <small class="text-muted">${user.status}</small>
                        </div>
                    </div>
                </div>
            `;
            
            onlineUsersContainer.insertAdjacentHTML('beforeend', userHtml);
            
            // Update count
            const onlineCountElement = document.getElementById('onlineCount');
            if (onlineCountElement) {
                const currentCount = parseInt(onlineCountElement.textContent) || 0;
                onlineCountElement.textContent = currentCount + 1;
            }
        }
        
        // Remove user from online list
        function removeUserFromList(userId) {
            const userItem = document.querySelector(`[data-user-id="${userId}"]`);
            if (userItem) {
                userItem.remove();
                
                // Update count
                const onlineCountElement = document.getElementById('onlineCount');
                if (onlineCountElement) {
                    const currentCount = parseInt(onlineCountElement.textContent) || 0;
                    onlineCountElement.textContent = Math.max(0, currentCount - 1);
                }
            }
        }
        
        // Add new room to the room list
        function addRoomToList(room) {
            const roomList = document.querySelector('.room-list');
            if (!roomList) return;
            
            const roomHtml = `
                <div class="room-item" data-room-id="${room.id}" onclick="joinRoom(${room.id})">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="flex-grow-1">
                            <div class="room-name">
                                <i class="fas fa-globe"></i>
                                ${room.name}
                            </div>
                            <small class="text-muted">${room.description || ''}</small>
                        </div>
                        <div class="room-meta">
                            <small class="text-muted">public</small>
                        </div>
                    </div>
                </div>
            `;
            
            // Add the new room to the beginning of the list
            roomList.insertAdjacentHTML('afterbegin', roomHtml);
        }
    </script>
</body>
</html>