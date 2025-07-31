<?php

namespace App\Models;

use CodeIgniter\Model;

class RoomParticipantModel extends Model
{
    protected $table = 'room_participants';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = ['room_id', 'user_id', 'is_admin'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = false;

    public function addUserToRoom($roomId, $userId, $isAdmin = false)
    {
        $existing = $this->where(['room_id' => $roomId, 'user_id' => $userId])->first();
        if (!$existing) {
            return $this->insert(['room_id' => $roomId, 'user_id' => $userId, 'is_admin' => $isAdmin]);
        }
        return true;
    }

    public function removeUserFromRoom($roomId, $userId)
    {
        return $this->where(['room_id' => $roomId, 'user_id' => $userId])->delete();
    }

    public function getRoomParticipants($roomId)
    {
        return $this->select('room_participants.*, users.username, users.status, users.avatar')
                   ->join('users', 'users.id = room_participants.user_id')
                   ->where('room_participants.room_id', $roomId)
                   ->orderBy('users.username', 'ASC')
                   ->findAll();
    }

    public function isUserInRoom($roomId, $userId)
    {
        return $this->where(['room_id' => $roomId, 'user_id' => $userId])->first() !== null;
    }

    public function getUserRoomIds($userId)
    {
        $result = $this->select('room_id')
                      ->where('user_id', $userId)
                      ->findAll();
        
        return array_column($result, 'room_id');
    }
    

}