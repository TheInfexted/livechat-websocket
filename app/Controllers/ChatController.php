<?php

namespace App\Controllers;

class ChatController extends BaseController
{
    public function index()
    {
        // Check authentication
        $authCheck = $this->requireAuth();
        if ($authCheck !== null) {
            return $authCheck;
        }

        $currentUser = $this->getCurrentUser();
        
        $data = [
            'rooms' => $this->roomModel->getUserRooms($currentUser['id']),
            'publicRooms' => $this->roomModel->getPublicRooms(),
            'onlineUsers' => $this->userModel->getOnlineUsers(),
            'currentUser' => $currentUser,
            'wsUrl' => getenv('websocket.url') ? getenv('websocket.url') : 'ws://localhost:8080'
        ];

        return view('chat/websocket_index', $data);
    }

    public function getRoomData($roomId)
    {
        $authCheck = $this->requireAuth();
        if ($authCheck !== null) {
            return $authCheck;
        }

        $room = $this->roomModel->find($roomId);
        if (!$room) {
            return $this->jsonError('Room not found', 404);
        }

        $currentUser = $this->getCurrentUser();
        
        // Check access for private rooms
        if (!$this->validateRoomAccess($room, $currentUser['id'])) {
            return $this->jsonError('Access denied', 403);
        }

        $data = [
            'room' => $room,
            'messages' => array_reverse($this->messageModel->getRoomMessages($roomId, 50)),
            'participants' => $this->participantModel->getRoomParticipants($roomId)
        ];

        return $this->jsonSuccess($data);
    }

    public function createRoom()
    {
        $authCheck = $this->requireAuth();
        if ($authCheck !== null) {
            return $authCheck;
        }

        // Handle both POST form data and JSON data
        $input = $this->request->getJSON();
        log_message('info', 'Room creation - Raw input: ' . json_encode($input));
        log_message('info', 'Room creation - POST data: ' . json_encode($this->request->getPost()));
        
        if ($input) {
            // JSON data
            $name = trim($input->name ?? '');
            $description = trim($input->description ?? '');
            $type = 'public'; // Always public
        } else {
            // POST form data
            $name = trim($this->request->getPost('name'));
            $description = trim($this->request->getPost('description'));
            $type = 'public'; // Always public
        }

        if (empty($name)) {
            return $this->jsonError('Room name required');
        }
        
        // Debug: Log the received data
        log_message('info', 'Room creation attempt - Name: ' . $name . ', Type: ' . $type);

        $currentUser = $this->getCurrentUser();
        
        // Sanitize messages
        $sanitizedName = $this->sanitizeMessage($name);
        $sanitizedDescription = $this->sanitizeMessage($description);
        
        $roomData = [
            'name' => $sanitizedName,
            'description' => $sanitizedDescription,
            'type' => $type,
            'created_by' => $currentUser['id']
        ];

        $roomId = $this->roomModel->insert($roomData);
        if ($roomId) {
            // Add creator as admin participant
            $this->participantModel->addUserToRoom($roomId, $currentUser['id'], true);
            
            // Create system message
            $this->messageModel->createSystemMessage(
                $roomId, 
                "Room '{$sanitizedName}' was created by " . $currentUser['username'], 
                $currentUser['id']
            );
            
            // Log activity
            $this->logChatActivity('room_created', [
                'room_id' => $roomId,
                'room_name' => $sanitizedName,
                'room_type' => $type
            ]);
            
            $room = $this->roomModel->find($roomId);
            return $this->jsonSuccess(['room' => $room], 'Room created successfully');
        }

        return $this->jsonError('Failed to create room');
    }

    public function joinRoom()
    {
        $authCheck = $this->requireAuth();
        if ($authCheck !== null) {
            return $authCheck;
        }

        $roomId = $this->request->getPost('room_id');
        $room = $this->roomModel->find($roomId);

        if (!$room) {
            return $this->jsonError('Room not found', 404);
        }

        $currentUser = $this->getCurrentUser();

        if ($this->participantModel->addUserToRoom($roomId, $currentUser['id'])) {
            // Create system message
            $this->messageModel->createSystemMessage($roomId, $currentUser['username'] . " joined the room");
            
            // Log activity
            $this->logChatActivity('room_joined', [
                'room_id' => $roomId,
                'room_name' => $room['name']
            ]);
            
            return $this->jsonSuccess([], 'Successfully joined room');
        }

        return $this->jsonError('Failed to join room');
    }

    public function leaveRoom()
    {
        $authCheck = $this->requireAuth();
        if ($authCheck !== null) {
            return $authCheck;
        }

        $roomId = $this->request->getPost('room_id');
        $room = $this->roomModel->find($roomId);

        if (!$room) {
            return $this->jsonError('Room not found', 404);
        }

        $currentUser = $this->getCurrentUser();

        if ($this->participantModel->removeUserFromRoom($roomId, $currentUser['id'])) {
            // Create system message
            $this->messageModel->createSystemMessage($roomId, $currentUser['username'] . " left the room");
            
            // Log activity
            $this->logChatActivity('room_left', [
                'room_id' => $roomId,
                'room_name' => $room['name']
            ]);
            
            return $this->jsonSuccess([], 'Successfully left room');
        }

        return $this->jsonError('Failed to leave room');
    }

    public function getOnlineUsers()
    {
        $authCheck = $this->requireAuth();
        if ($authCheck !== null) {
            return $authCheck;
        }

        // Clean up old connections
        $this->connectionModel->cleanupStaleConnections();
        
        $onlineUsers = $this->userModel->getOnlineUsers();
        return $this->jsonSuccess(['users' => $onlineUsers]);
    }

    public function updateUserStatus()
    {
        $authCheck = $this->requireAuth();
        if ($authCheck !== null) {
            return $authCheck;
        }

        $status = $this->request->getPost('status') ?? 'online';
        
        if (in_array($status, ['online', 'away', 'offline'])) {
            $currentUser = $this->getCurrentUser();
            
            if ($this->userModel->updateUserStatus($currentUser['id'], $status)) {
                // Log activity
                $this->logChatActivity('status_update', ['status' => $status]);
                
                return $this->jsonSuccess([], 'Status updated successfully');
            }
        }

        return $this->jsonError('Failed to update status');
    }
}