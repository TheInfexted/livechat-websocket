<?php

namespace App\Models;

use CodeIgniter\Model;

class MessageModel extends Model
{
    protected $table = 'messages';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = ['room_id', 'user_id', 'message', 'message_type', 'file_path', 'is_edited'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    public function getRoomMessages($roomId, $limit = 50, $offset = 0)
    {
        return $this->select('messages.*, users.username, users.avatar')
                   ->join('users', 'users.id = messages.user_id')
                   ->where('messages.room_id', $roomId)
                   ->orderBy('messages.created_at', 'DESC')
                   ->limit($limit, $offset)
                   ->findAll();
    }

    public function getLatestMessages($roomId, $lastMessageId = 0)
    {
        $builder = $this->select('messages.*, users.username, users.avatar')
                       ->join('users', 'users.id = messages.user_id')
                       ->where('messages.room_id', $roomId);
        
        if ($lastMessageId > 0) {
            $builder->where('messages.id >', $lastMessageId);
        }
        
        return $builder->orderBy('messages.created_at', 'ASC')->findAll();
    }

    public function createSystemMessage($roomId, $message, $userId = null)
    {
        return $this->insert([
            'room_id' => $roomId,
            'user_id' => $userId ?? 1, // Use system user or admin
            'message' => $message,
            'message_type' => 'system'
        ]);
    }
}