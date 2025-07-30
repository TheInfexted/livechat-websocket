<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = ['username', 'email', 'password', 'avatar', 'status', 'last_seen'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    // Validation
    protected $validationRules = [
        'username' => 'required|min_length[3]|max_length[50]|is_unique[users.username]',
        'email' => 'required|valid_email|is_unique[users.email]',
        'password' => 'required|min_length[6]'
    ];
    protected $validationMessages = [];
    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    public function updateUserStatus($userId, $status)
    {
        return $this->update($userId, ['status' => $status, 'last_seen' => date('Y-m-d H:i:s')]);
    }

    public function getOnlineUsers()
    {
        return $this->where('status', 'online')
                   ->orderBy('username', 'ASC')
                   ->findAll();
    }

    public function getUserByCredentials($email, $password)
    {
        $user = $this->where('email', $email)->first();
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }

    public function generateSessionToken($userId)
    {
        // Simple session token for demo 
        return bin2hex(random_bytes(32));
    }
}