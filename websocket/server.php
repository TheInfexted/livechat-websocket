<?php
// websocket/server.php - Clean WebSocket Server

require_once __DIR__ . '/../vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class ChatServer implements MessageComponentInterface
{
    protected $clients;
    protected $rooms;
    protected $userConnections;
    protected $db;
    protected $stats;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->rooms = [];
        $this->userConnections = [];
        $this->stats = [
            'connections' => 0,
            'messages_sent' => 0,
            'start_time' => time()
        ];
        
        $this->initializeDatabase();
        echo "Chat server initialized successfully\n";
    }

    private function initializeDatabase()
    {
        try {
            // Try different connection methods for MAMP
            $connections = [
                "mysql:host=localhost;port=3306;dbname=livechat_db;charset=utf8mb4",
                "mysql:host=127.0.0.1;port=3306;dbname=livechat_db;charset=utf8mb4",
                "mysql:unix_socket=/Applications/MAMP/tmp/mysql/mysql.sock;dbname=livechat_db;charset=utf8mb4"
            ];
            
            $connected = false;
            foreach ($connections as $dsn) {
                try {
                    $this->db = new PDO($dsn, 'root', 'root', [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]);
                    echo "Database connection established using: " . $dsn . "\n";
                    $connected = true;
                    break;
                } catch (Exception $e) {
                    continue;
                }
            }
            
            if (!$connected) {
                throw new Exception("Could not connect to MySQL with any method");
            }
            
        } catch (Exception $e) {
            echo "Database connection failed: " . $e->getMessage() . "\n";
            echo "Please make sure MAMP MySQL is running on port 3306\n";
            echo "Check MAMP preferences -> Ports -> MySQL Port: 3306\n";
            throw $e;
        }
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        $this->stats['connections']++;
        
        $conn->chatData = [
            'id' => uniqid('conn_', true),
            'user_id' => null,
            'username' => null,
            'room_id' => null,
            'authenticated' => false,
            'connected_at' => time(),
            'message_times' => []
        ];

        echo "[" . date('Y-m-d H:i:s') . "] New connection: {$conn->resourceId} (Total: {$this->stats['connections']})\n";
        
        $conn->send(json_encode([
            'type' => 'connection_established',
            'connection_id' => $conn->chatData['id'],
            'server_time' => date('c')
        ]));
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        try {
            $data = json_decode($msg, true);
            
            if (!$data || !isset($data['type'])) {
                $this->sendError($from, 'Invalid message format');
                return;
            }

            // Rate limiting
            if (!$this->checkRateLimit($from)) {
                $this->sendError($from, 'Rate limit exceeded');
                return;
            }

            switch ($data['type']) {
                case 'authenticate':
                    $this->handleAuthentication($from, $data);
                    break;
                    
                case 'join_room':
                    $this->handleJoinRoom($from, $data);
                    break;
                    
                case 'leave_room':
                    $this->handleLeaveRoom($from, $data);
                    break;
                    
                case 'send_message':
                    $this->handleSendMessage($from, $data);
                    break;
                    
                case 'typing_start':
                    $this->handleTypingStart($from, $data);
                    break;
                    
                case 'typing_stop':
                    $this->handleTypingStop($from, $data);
                    break;
                    
                case 'ping':
                    $this->handlePing($from);
                    break;
                    
                default:
                    $this->sendError($from, 'Unknown message type: ' . $data['type']);
            }
        } catch (Exception $e) {
            echo "Error handling message: " . $e->getMessage() . "\n";
            $this->sendError($from, 'Server error occurred');
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        $this->stats['connections']--;
        
        if ($conn->chatData['authenticated']) {
            $this->handleUserDisconnection($conn);
        }

        echo "[" . date('Y-m-d H:i:s') . "] Connection closed: {$conn->resourceId} (Total: {$this->stats['connections']})\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "[" . date('Y-m-d H:i:s') . "] Connection error: {$e->getMessage()}\n";
        $conn->close();
    }

    private function checkRateLimit(ConnectionInterface $conn)
    {
        $now = time();
        
        // Remove old timestamps (older than 1 minute)
        $conn->chatData['message_times'] = array_filter(
            $conn->chatData['message_times'],
            function($time) use ($now) {
                return ($now - $time) < 60;
            }
        );
        
        // Allow max 30 messages per minute
        if (count($conn->chatData['message_times']) >= 30) {
            return false;
        }
        
        $conn->chatData['message_times'][] = $now;
        return true;
    }

    private function handleAuthentication($conn, $data)
    {
        if (!isset($data['user_id']) || !isset($data['session_token'])) {
            $this->sendError($conn, 'Missing authentication data');
            return;
        }

        try {
            $stmt = $this->db->prepare("SELECT id, username, email, status FROM users WHERE id = ?");
            $stmt->execute([$data['user_id']]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $this->sendError($conn, 'Invalid user');
                return;
            }

            $conn->chatData['user_id'] = $user['id'];
            $conn->chatData['username'] = $user['username'];
            $conn->chatData['authenticated'] = true;

            // Store connection in database
            $stmt = $this->db->prepare(
                "INSERT INTO websocket_connections (user_id, connection_id, socket_id, ip_address, connected_at, last_ping) 
                 VALUES (?, ?, ?, ?, NOW(), NOW())"
            );
            $stmt->execute([
                $user['id'],
                $conn->chatData['id'],
                $conn->resourceId,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            // Store in memory
            if (!isset($this->userConnections[$user['id']])) {
                $this->userConnections[$user['id']] = [];
            }
            $this->userConnections[$user['id']][$conn->chatData['id']] = $conn;

            // Update user status to online
            $stmt = $this->db->prepare("UPDATE users SET status = 'online', last_seen = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);

            $conn->send(json_encode([
                'type' => 'authenticated',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'status' => 'online'
                ]
            ]));

            $this->broadcastUserStatusUpdate($user['id'], 'online');
            
            echo "[" . date('Y-m-d H:i:s') . "] User authenticated: {$user['username']}\n";
            
        } catch (Exception $e) {
            echo "Authentication error: " . $e->getMessage() . "\n";
            $this->sendError($conn, 'Authentication failed');
        }
    }

    private function handleJoinRoom($conn, $data)
    {
        if (!$conn->chatData['authenticated']) {
            $this->sendError($conn, 'Not authenticated');
            return;
        }

        $roomId = $data['room_id'] ?? null;
        if (!$roomId) {
            $this->sendError($conn, 'Room ID required');
            return;
        }

        try {
            // Verify room exists
            $stmt = $this->db->prepare("SELECT * FROM chat_rooms WHERE id = ?");
            $stmt->execute([$roomId]);
            $room = $stmt->fetch();
            
            if (!$room) {
                $this->sendError($conn, 'Room not found');
                return;
            }

            // Check access for private rooms
            if ($room['type'] === 'private') {
                $stmt = $this->db->prepare(
                    "SELECT * FROM room_participants WHERE room_id = ? AND user_id = ?"
                );
                $stmt->execute([$roomId, $conn->chatData['user_id']]);
                
                if (!$stmt->fetch()) {
                    $this->sendError($conn, 'Access denied to private room');
                    return;
                }
            }

            // Leave current room if any
            if ($conn->chatData['room_id']) {
                $this->removeFromRoom($conn, $conn->chatData['room_id']);
            }

            // Join new room
            $conn->chatData['room_id'] = $roomId;
            
            if (!isset($this->rooms[$roomId])) {
                $this->rooms[$roomId] = [];
            }
            $this->rooms[$roomId][$conn->chatData['id']] = $conn;

            // Add to room participants if not already there
            $stmt = $this->db->prepare(
                "INSERT IGNORE INTO room_participants (room_id, user_id) VALUES (?, ?)"
            );
            $stmt->execute([$roomId, $conn->chatData['user_id']]);

            // Get recent messages
            $stmt = $this->db->prepare(
                "SELECT m.*, u.username, u.avatar 
                 FROM messages m 
                 JOIN users u ON u.id = m.user_id 
                 WHERE m.room_id = ? 
                 ORDER BY m.created_at DESC 
                 LIMIT 50"
            );
            $stmt->execute([$roomId]);
            $messages = array_reverse($stmt->fetchAll());

            // Get participants
            $stmt = $this->db->prepare(
                "SELECT rp.*, u.username, u.status, u.avatar 
                 FROM room_participants rp 
                 JOIN users u ON u.id = rp.user_id 
                 WHERE rp.room_id = ?"
            );
            $stmt->execute([$roomId]);
            $participants = $stmt->fetchAll();

            $conn->send(json_encode([
                'type' => 'room_joined',
                'room' => $room,
                'messages' => $messages,
                'participants' => $participants
            ]));

            // Broadcast user joined to other room members
            $this->broadcastToRoom($roomId, json_encode([
                'type' => 'user_joined_room',
                'user' => [
                    'id' => $conn->chatData['user_id'],
                    'username' => $conn->chatData['username']
                ],
                'room_id' => $roomId
            ]), $conn->chatData['id']);

            echo "[" . date('Y-m-d H:i:s') . "] User {$conn->chatData['username']} joined room {$roomId}\n";
            
        } catch (Exception $e) {
            echo "Join room error: " . $e->getMessage() . "\n";
            $this->sendError($conn, 'Failed to join room');
        }
    }

    private function handleSendMessage($conn, $data)
    {
        if (!$conn->chatData['authenticated'] || !$conn->chatData['room_id']) {
            $this->sendError($conn, 'Not in a room');
            return;
        }

        $message = trim($data['message'] ?? '');
        if (empty($message)) {
            $this->sendError($conn, 'Message cannot be empty');
            return;
        }

        try {
            // Save message to database
            $stmt = $this->db->prepare(
                "INSERT INTO messages (room_id, user_id, message, message_type) VALUES (?, ?, ?, 'text')"
            );
            $stmt->execute([$conn->chatData['room_id'], $conn->chatData['user_id'], $message]);

            $messageId = $this->db->lastInsertId();

            // Get the complete message data
            $stmt = $this->db->prepare(
                "SELECT m.*, u.username, u.avatar 
                 FROM messages m 
                 JOIN users u ON u.id = m.user_id 
                 WHERE m.id = ?"
            );
            $stmt->execute([$messageId]);
            $messageData = $stmt->fetch();

            // Broadcast message to all room members
            $broadcastData = json_encode([
                'type' => 'new_message',
                'message' => $messageData,
                'room_id' => $conn->chatData['room_id']
            ]);

            $this->broadcastToRoom($conn->chatData['room_id'], $broadcastData);
            $this->stats['messages_sent']++;

            echo "[" . date('Y-m-d H:i:s') . "] Message sent by {$conn->chatData['username']} in room {$conn->chatData['room_id']}\n";
            
        } catch (Exception $e) {
            echo "Send message error: " . $e->getMessage() . "\n";
            $this->sendError($conn, 'Failed to send message');
        }
    }

    private function handleTypingStart($conn, $data)
    {
        if (!$conn->chatData['authenticated'] || !$conn->chatData['room_id']) {
            return;
        }

        $this->broadcastToRoom($conn->chatData['room_id'], json_encode([
            'type' => 'user_typing_start',
            'user' => [
                'id' => $conn->chatData['user_id'],
                'username' => $conn->chatData['username']
            ],
            'room_id' => $conn->chatData['room_id']
        ]), $conn->chatData['id']);
    }

    private function handleTypingStop($conn, $data)
    {
        if (!$conn->chatData['authenticated'] || !$conn->chatData['room_id']) {
            return;
        }

        $this->broadcastToRoom($conn->chatData['room_id'], json_encode([
            'type' => 'user_typing_stop',
            'user' => [
                'id' => $conn->chatData['user_id'],
                'username' => $conn->chatData['username']
            ],
            'room_id' => $conn->chatData['room_id']
        ]), $conn->chatData['id']);
    }

    private function handlePing($conn)
    {
        $conn->send(json_encode(['type' => 'pong']));
    }

    private function handleLeaveRoom($conn, $data)
    {
        if (!$conn->chatData['authenticated'] || !$conn->chatData['room_id']) {
            return;
        }

        $this->removeFromRoom($conn, $conn->chatData['room_id']);
    }

    private function removeFromRoom($conn, $roomId)
    {
        // Remove from room array
        if (isset($this->rooms[$roomId][$conn->chatData['id']])) {
            unset($this->rooms[$roomId][$conn->chatData['id']]);
            
            if (empty($this->rooms[$roomId])) {
                unset($this->rooms[$roomId]);
            }
        }

        // Broadcast user left
        $this->broadcastToRoom($roomId, json_encode([
            'type' => 'user_left_room',
            'user' => [
                'id' => $conn->chatData['user_id'],
                'username' => $conn->chatData['username']
            ],
            'room_id' => $roomId
        ]), $conn->chatData['id']);

        $conn->chatData['room_id'] = null;
    }

    private function broadcastToRoom($roomId, $message, $excludeConnectionId = null)
    {
        if (!isset($this->rooms[$roomId])) {
            return;
        }

        foreach ($this->rooms[$roomId] as $connectionId => $conn) {
            if ($excludeConnectionId && $connectionId === $excludeConnectionId) {
                continue;
            }
            
            try {
                $conn->send($message);
            } catch (Exception $e) {
                echo "Error sending message to connection {$connectionId}: " . $e->getMessage() . "\n";
            }
        }
    }

    private function broadcastUserStatusUpdate($userId, $status)
    {
        $message = json_encode([
            'type' => 'user_status_update',
            'user_id' => $userId,
            'status' => $status,
            'timestamp' => date('c')
        ]);

        foreach ($this->clients as $client) {
            if ($client->chatData['authenticated']) {
                try {
                    $client->send($message);
                } catch (Exception $e) {
                    echo "Error broadcasting status update: " . $e->getMessage() . "\n";
                }
            }
        }
    }

    private function handleUserDisconnection($conn)
    {
        try {
            // Remove from room
            if ($conn->chatData['room_id']) {
                $this->removeFromRoom($conn, $conn->chatData['room_id']);
            }
            
            // Remove from user connections
            if (isset($this->userConnections[$conn->chatData['user_id']])) {
                unset($this->userConnections[$conn->chatData['user_id']][$conn->chatData['id']]);
                if (empty($this->userConnections[$conn->chatData['user_id']])) {
                    unset($this->userConnections[$conn->chatData['user_id']]);
                    
                    // Set user offline if no other connections
                    $stmt = $this->db->prepare("UPDATE users SET status = 'offline', last_seen = NOW() WHERE id = ?");
                    $stmt->execute([$conn->chatData['user_id']]);
                    $this->broadcastUserStatusUpdate($conn->chatData['user_id'], 'offline');
                }
            }
            
            // Remove from database
            $stmt = $this->db->prepare("DELETE FROM websocket_connections WHERE connection_id = ?");
            $stmt->execute([$conn->chatData['id']]);
            
        } catch (Exception $e) {
            echo "Error handling user disconnection: " . $e->getMessage() . "\n";
        }
    }

    private function sendError($conn, $message)
    {
        $conn->send(json_encode([
            'type' => 'error',
            'message' => $message,
            'timestamp' => date('c')
        ]));
    }

    public function shutdown()
    {
        echo "Shutting down WebSocket server...\n";
        
        foreach ($this->clients as $client) {
            try {
                $client->send(json_encode([
                    'type' => 'server_shutdown',
                    'message' => 'Server is shutting down'
                ]));
                $client->close();
            } catch (Exception $e) {
                // Ignore errors during shutdown
            }
        }
        
        try {
            $stmt = $this->db->prepare("UPDATE users SET status = 'offline', last_seen = NOW() WHERE status = 'online'");
            $stmt->execute();
            
            $stmt = $this->db->prepare("DELETE FROM websocket_connections");
            $stmt->execute();
        } catch (Exception $e) {
            echo "Error during cleanup: " . $e->getMessage() . "\n";
        }
        
        echo "Server shutdown complete\n";
    }
}

// Start the server
echo "=================================\n";
echo "Live Chat WebSocket Server\n";
echo "=================================\n";
echo "Starting server on 0.0.0.0:8080\n";
echo "Database: livechat_db (MySQL:3306)\n";
echo "Press Ctrl+C to stop the server\n";
echo "=================================\n\n";

try {
    $chatServer = new ChatServer();
    
    $server = IoServer::factory(
        new HttpServer(
            new WsServer($chatServer)
        ),
        8080,
        '0.0.0.0'
    );

    // Handle shutdown gracefully
    if (function_exists('pcntl_signal')) {
        pcntl_signal(SIGTERM, function() use ($chatServer) {
            echo "\nReceived SIGTERM, shutting down gracefully...\n";
            $chatServer->shutdown();
            exit(0);
        });
        
        pcntl_signal(SIGINT, function() use ($chatServer) {
            echo "\nReceived SIGINT, shutting down gracefully...\n";
            $chatServer->shutdown();
            exit(0);
        });
    }

    echo "WebSocket server started successfully!\n";
    echo "WebSocket URL: ws://localhost:8080\n\n";
    
    $server->run();
    
} catch (Exception $e) {
    echo "Error starting server: " . $e->getMessage() . "\n";
    exit(1);
}