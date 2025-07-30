<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Default route
$routes->get('/', 'Home::index');

// Authentication routes
$routes->group('', function($routes) {
    $routes->get('login', 'AuthController::login');
    $routes->post('login', 'AuthController::login');
    $routes->get('register', 'AuthController::register');
    $routes->post('register', 'AuthController::register');
    $routes->get('logout', 'AuthController::logout');
});

// Chat routes (protected by auth filter)
$routes->group('chat', ['filter' => 'auth'], function($routes) {
    $routes->get('/', 'ChatController::index');
    $routes->get('room/(:num)', 'ChatController::getRoomData/$1');
    
    // POST endpoints
    $routes->post('create-room', 'ChatController::createRoom');
    $routes->post('join-room', 'ChatController::joinRoom');
    $routes->post('leave-room', 'ChatController::leaveRoom');
    $routes->post('update-status', 'ChatController::updateUserStatus');
    
    // GET endpoints
    $routes->get('online-users', 'ChatController::getOnlineUsers');
});

// API routes for AJAX calls (protected by auth filter)
$routes->group('api', ['filter' => 'auth'], function($routes) {
    // GET endpoints
    $routes->get('room/(:num)/data', 'ChatController::getRoomData/$1');
    $routes->get('users/online', 'ChatController::getOnlineUsers');
    
    // POST endpoints
    $routes->post('user/status', 'ChatController::updateUserStatus');
    $routes->post('room/create', 'ChatController::createRoom');
    $routes->post('room/join', 'ChatController::joinRoom');
    $routes->post('room/leave', 'ChatController::leaveRoom');
});

// General utility routes
$routes->group('general', function($routes) {
    // GET endpoints
    $routes->get('format-time', 'General::formatTime');
    $routes->get('time-ago', 'General::timeAgo');
    $routes->get('generate-token', 'General::generateToken');
    $routes->get('avatar-url', 'General::getAvatarUrl');
    $routes->get('is-online', 'General::isOnline');
    $routes->get('status-badge', 'General::getStatusBadge');
    $routes->get('truncate-text', 'General::truncateText');
    $routes->get('format-file-size', 'General::formatFileSize');
    $routes->get('generate-room-code', 'General::generateRoomCode');
    
    // POST endpoints
    $routes->post('sanitize-message', 'General::sanitizeMessage');
    $routes->post('validate-room-access', 'General::validateRoomAccess');
    $routes->post('log-activity', 'General::logActivity');
    $routes->post('validate-image-type', 'General::validateImageType');
});