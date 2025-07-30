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
                                <li><a class="dropdown-item" href="#" onclick="updateStatus('online')">
                                    <i class="fas fa-circle text-success"></i> Online
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="updateStatus('away')">
                                    <i class="fas fa-circle text-warning"></i> Away
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
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
                                        <i class="fas <?= $room['type'] === 'private' ? 'fa-lock' : 'fa-globe' ?>"></i>
                                        <strong><?= esc($room['name']) ?></strong>
                                    </div>
                                    <small class="text-muted"><?= $room['type'] ?></small>
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
                                <button class="btn btn-sm btn-outline-secondary" onclick="loadMoreMessages()">
                                    <i class="fas fa-history"></i> History
                                </button>
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
                                <option value="private">Private - Invitation only</option>
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
    </script>
</body>
</html>