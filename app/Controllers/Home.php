<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        if ($this->isAuthenticated()) {
            return redirect()->to('/chat');
        }
        return redirect()->to('/login');
    }
}