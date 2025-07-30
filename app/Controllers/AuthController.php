<?php

namespace App\Controllers;

class AuthController extends BaseController
{
    public function login()
    {
        if ($this->isAuthenticated()) {
            return redirect()->to('/chat');
        }

        if ($this->request->getMethod() === 'POST') {
            $email = $this->request->getPost('email');
            $password = $this->request->getPost('password');

            $user = $this->userModel->getUserByCredentials($email, $password);
            if ($user) {
                // Generate session token for WebSocket authentication
                $sessionToken = $this->generateSessionToken();
                
                $this->session->set([
                    'user_id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'session_token' => $sessionToken,
                    'logged_in' => true
                ]);

                // Update user status to online
                $this->userModel->updateUserStatus($user['id'], 'online');

                // Log activity
                $this->logChatActivity('user_login', ['user_id' => $user['id'], 'username' => $user['username']]);

                return redirect()->to('/chat');
            } else {
                return redirect()->back()->with('error', 'Invalid credentials');
            }
        }

        return view('auth/login');
    }

    public function register()
    {
        if ($this->isAuthenticated()) {
            return redirect()->to('/chat');
        }

        if ($this->request->getMethod() === 'POST') {
            $data = [
                'username' => $this->request->getPost('username'),
                'email' => $this->request->getPost('email'),
                'password' => password_hash($this->request->getPost('password'), PASSWORD_DEFAULT)
            ];

            if ($this->userModel->insert($data)) {
                // Log activity
                $this->logChatActivity('user_register', ['username' => $data['username'], 'email' => $data['email']]);
                
                return redirect()->to('/login')->with('success', 'Registration successful! You can now login.');
            } else {
                $errors = $this->userModel->errors();
                return redirect()->back()->with('error', implode(', ', $errors))->withInput();
            }
        }

        return view('auth/register');
    }

    public function logout()
    {
        $userId = $this->session->get('user_id');
        if ($userId) {
            $this->userModel->updateUserStatus($userId, 'offline');
            $this->logChatActivity('user_logout', ['user_id' => $userId]);
        }
        
        $this->session->destroy();
        return redirect()->to('/login');
    }
}