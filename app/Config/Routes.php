<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

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
