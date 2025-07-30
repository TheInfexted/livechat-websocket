<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * General Controller
 * 
 * Handles general utility operations and helper endpoints
 */
class General extends Controller
{
    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var array
     */
    protected $helpers = ['form', 'url'];

    /**
     * Constructor.
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        // E.g.: $this->session = \Config\Services::session();
    }

    /**
     * Format timestamp for display
     * GET /general/format-time
     */
    public function formatTime()
    {
        $timestamp = $this->request->getGet('timestamp');
        $format = $this->request->getGet('format') ?? 'Y-m-d H:i:s';
        
        if (!$timestamp) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Timestamp parameter is required'
            ])->setStatusCode(400);
        }

        $formatted = date($format, strtotime($timestamp));
        
        return $this->response->setJSON([
            'success' => true,
            'formatted_time' => $formatted
        ]);
    }

    /**
     * Get time ago format
     * GET /general/time-ago
     */
    public function timeAgo()
    {
        $timestamp = $this->request->getGet('timestamp');
        
        if (!$timestamp) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Timestamp parameter is required'
            ])->setStatusCode(400);
        }

        $time = time() - strtotime($timestamp);
        
        if ($time < 60) {
            $result = 'just now';
        } elseif ($time < 3600) {
            $minutes = floor($time / 60);
            $result = $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($time < 86400) {
            $hours = floor($time / 3600);
            $result = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($time < 2592000) {
            $days = floor($time / 86400);
            $result = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            $result = date('M j, Y', strtotime($timestamp));
        }
        
        return $this->response->setJSON([
            'success' => true,
            'time_ago' => $result
        ]);
    }

    /**
     * Generate secure session token
     * GET /general/generate-token
     */
    public function generateToken()
    {
        $length = (int)($this->request->getGet('length') ?? 32);
        $token = bin2hex(random_bytes($length));
        
        return $this->response->setJSON([
            'success' => true,
            'token' => $token
        ]);
    }

    /**
     * Sanitize chat message
     * POST /general/sanitize-message
     */
    public function sanitizeMessage()
    {
        $message = $this->request->getPost('message');
        
        if (!$message) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Message parameter is required'
            ])->setStatusCode(400);
        }

        // Remove potentially harmful content
        $sanitized = strip_tags($message);
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');
        $sanitized = trim($sanitized);
        
        // Limit message length
        if (strlen($sanitized) > 1000) {
            $sanitized = substr($sanitized, 0, 1000) . '...';
        }
        
        return $this->response->setJSON([
            'success' => true,
            'sanitized_message' => $sanitized
        ]);
    }

    /**
     * Get user avatar URL or generate initials
     * GET /general/avatar-url
     */
    public function getAvatarUrl()
    {
        $avatar = $this->request->getGet('avatar');
        $username = $this->request->getGet('username');
        
        if (!$username) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Username parameter is required'
            ])->setStatusCode(400);
        }

        if ($avatar && file_exists(FCPATH . 'uploads/avatars/' . $avatar)) {
            $url = base_url('uploads/avatars/' . $avatar);
        } else {
            // Return initials-based avatar
            $initials = strtoupper(substr($username, 0, 1));
            $url = "data:image/svg+xml;base64," . base64_encode(
                '<svg width="40" height="40" xmlns="http://www.w3.org/2000/svg">
                    <rect width="40" height="40" fill="#007bff"/>
                    <text x="20" y="25" font-size="16" fill="white" text-anchor="middle" font-family="Arial">' . $initials . '</text>
                </svg>'
            );
        }
        
        return $this->response->setJSON([
            'success' => true,
            'avatar_url' => $url
        ]);
    }

    /**
     * Check if user is online based on last activity
     * GET /general/is-online
     */
    public function isOnline()
    {
        $lastSeen = $this->request->getGet('last_seen');
        $minutesThreshold = (int)($this->request->getGet('threshold') ?? 5);
        
        if (!$lastSeen) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Last seen parameter is required'
            ])->setStatusCode(400);
        }

        $threshold = time() - ($minutesThreshold * 60);
        $isOnline = strtotime($lastSeen) > $threshold;
        
        return $this->response->setJSON([
            'success' => true,
            'is_online' => $isOnline
        ]);
    }

    /**
     * Get HTML status badge for user
     * GET /general/status-badge
     */
    public function getStatusBadge()
    {
        $status = $this->request->getGet('status') ?? 'offline';
        
        $badges = [
            'online' => '<span class="badge bg-success">Online</span>',
            'away' => '<span class="badge bg-warning">Away</span>',
            'offline' => '<span class="badge bg-secondary">Offline</span>'
        ];
        
        $badge = $badges[$status] ?? $badges['offline'];
        
        return $this->response->setJSON([
            'success' => true,
            'badge' => $badge
        ]);
    }

    /**
     * Truncate text to specified length
     * GET /general/truncate-text
     */
    public function truncateText()
    {
        $text = $this->request->getGet('text');
        $length = (int)($this->request->getGet('length') ?? 50);
        $suffix = $this->request->getGet('suffix') ?? '...';
        
        if (!$text) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Text parameter is required'
            ])->setStatusCode(400);
        }

        if (strlen($text) <= $length) {
            $truncated = $text;
        } else {
            $truncated = substr($text, 0, $length - strlen($suffix)) . $suffix;
        }
        
        return $this->response->setJSON([
            'success' => true,
            'truncated_text' => $truncated
        ]);
    }

    /**
     * Validate if user has access to room
     * POST /general/validate-room-access
     */
    public function validateRoomAccess()
    {
        $room = $this->request->getPost('room');
        $userId = (int)$this->request->getPost('user_id');
        
        if (!$room || !$userId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Room and user_id parameters are required'
            ])->setStatusCode(400);
        }

        // Public rooms are accessible to everyone
        if ($room['type'] === 'public') {
            $hasAccess = true;
        } elseif ($room['type'] === 'private') {
            // Private rooms require participant check
            $participantModel = new \App\Models\RoomParticipantModel();
            $hasAccess = $participantModel->isUserInRoom($room['id'], $userId);
        } else {
            $hasAccess = false;
        }
        
        return $this->response->setJSON([
            'success' => true,
            'has_access' => $hasAccess
        ]);
    }

    /**
     * Log chat activity for debugging/monitoring
     * POST /general/log-activity
     */
    public function logActivity()
    {
        $action = $this->request->getPost('action');
        $data = $this->request->getPost('data') ?? [];
        
        if (!$action) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Action parameter is required'
            ])->setStatusCode(400);
        }

        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => $action,
            'user_id' => session('user_id'),
            'ip_address' => $this->request->getIPAddress(),
            'data' => $data
        ];
        
        log_message('info', 'Chat Activity: ' . json_encode($logData));
        
        return $this->response->setJSON([
            'success' => true,
            'message' => 'Activity logged successfully'
        ]);
    }

    /**
     * Format file size in human readable format
     * GET /general/format-file-size
     */
    public function formatFileSize()
    {
        $bytes = (int)$this->request->getGet('bytes');
        
        if ($bytes < 0) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Bytes must be a positive number'
            ])->setStatusCode(400);
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        $formatted = round($bytes, 2) . ' ' . $units[$pow];
        
        return $this->response->setJSON([
            'success' => true,
            'formatted_size' => $formatted
        ]);
    }

    /**
     * Check if file is valid image type
     * POST /general/validate-image-type
     */
    public function validateImageType()
    {
        $mimeType = $this->request->getPost('mime_type');
        
        if (!$mimeType) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'MIME type parameter is required'
            ])->setStatusCode(400);
        }

        $allowedTypes = [
            'image/jpeg',
            'image/jpg', 
            'image/png',
            'image/gif',
            'image/webp'
        ];
        
        $isValid = in_array(strtolower($mimeType), $allowedTypes);
        
        return $this->response->setJSON([
            'success' => true,
            'is_valid' => $isValid
        ]);
    }

    /**
     * Generate unique room invitation code
     * GET /general/generate-room-code
     */
    public function generateRoomCode()
    {
        $length = (int)($this->request->getGet('length') ?? 8);
        $code = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length));
        
        return $this->response->setJSON([
            'success' => true,
            'room_code' => $code
        ]);
    }
}