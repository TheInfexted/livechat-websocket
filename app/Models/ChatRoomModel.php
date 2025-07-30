<?php

namespace App\Models;

use CodeIgniter\Model;

class ChatRoomModel extends Model
{
    protected $table = 'chat_rooms';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = ['name', 'description', 'type', 'created_by'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    public function getRoomsWithParticipants()
    {
        return $this->select('chat_rooms.*, users.username as creator_name, COUNT(room_participants.user_id) as participant_count')
                   ->join('users', 'users.id = chat_rooms.created_by')
                   ->join('room_participants', 'room_participants.room_id = chat_rooms.id', 'left')
                   ->groupBy('chat_rooms.id')
                   ->orderBy('chat_rooms.name', 'ASC')
                   ->findAll();
    }

    public function getUserRooms($userId)
    {
        return $this->select('chat_rooms.*')
                   ->join('room_participants', 'room_participants.room_id = chat_rooms.id', 'left')
                   ->where('room_participants.user_id', $userId)
                   ->orWhere('chat_rooms.type', 'public')
                   ->groupBy('chat_rooms.id')
                   ->orderBy('chat_rooms.name', 'ASC')
                   ->findAll();
    }

    public function getPublicRooms()
    {
        return $this->where('type', 'public')
                   ->orderBy('name', 'ASC')
                   ->findAll();
    }
}