<?php

namespace App\Models;

use CodeIgniter\Model;

class WebSocketConnectionModel extends Model
{
    protected $table = 'websocket_connections';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = ['user_id', 'connection_id', 'room_id', 'socket_id', 'ip_address', 'user_agent', 'last_ping'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = false;

    public function createConnection($userId, $connectionId, $socketId, $ipAddress = null, $userAgent = null)
    {
        return $this->insert([
            'user_id' => $userId,
            'connection_id' => $connectionId,
            'socket_id' => $socketId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'connected_at' => date('Y-m-d H:i:s'),
            'last_ping' => date('Y-m-d H:i:s')
        ]);
    }

    public function updateConnectionRoom($connectionId, $roomId)
    {
        return $this->where('connection_id', $connectionId)
                    ->set(['room_id' => $roomId, 'last_ping' => date('Y-m-d H:i:s')])
                    ->update();
    }

    public function removeConnection($connectionId)
    {
        return $this->where('connection_id', $connectionId)->delete();
    }

    public function getUserConnections($userId)
    {
        return $this->where('user_id', $userId)->findAll();
    }

    public function getRoomConnections($roomId)
    {
        return $this->select('websocket_connections.*, users.username')
                   ->join('users', 'users.id = websocket_connections.user_id')
                   ->where('websocket_connections.room_id', $roomId)
                   ->findAll();
    }

    public function cleanupStaleConnections($minutes = 5)
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
        return $this->where('last_ping <', $cutoff)->delete();
    }

    public function updateLastPing($connectionId)
    {
        return $this->where('connection_id', $connectionId)
                    ->set('last_ping', date('Y-m-d H:i:s'))
                    ->update();
    }

    public function getActiveConnections()
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-2 minutes'));
        return $this->select('websocket_connections.*, users.username, users.status')
                   ->join('users', 'users.id = websocket_connections.user_id')
                   ->where('websocket_connections.last_ping >', $cutoff)
                   ->findAll();
    }
}