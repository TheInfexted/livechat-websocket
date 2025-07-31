<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

// Models
use App\Models\UserModel;
use App\Models\ChatRoomModel;
use App\Models\MessageModel;
use App\Models\RoomParticipantModel;
use App\Models\WebSocketConnectionModel;

// Controllers
use App\Controllers\General;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 *     class Home extends BaseController
 *
 * For security be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var list<string>
     */
    protected $helpers = ['general'];

    /**
     * Common Models - Available to all controllers
     */
    protected $userModel;
    protected $roomModel;
    protected $messageModel;
    protected $participantModel;
    protected $connectionModel;

    /**
     * Session instance
     */
    protected $session;

    /**
     * Create a properly initialized General controller instance
     */
    private function createGeneralController(array $data = []): General
    {
        $generalController = new General();
        
        // Create temporary request
        $tempRequest = new IncomingRequest(
            new \Config\App(),
            new \CodeIgniter\HTTP\URI(),
            json_encode($data),
            new \CodeIgniter\HTTP\UserAgent()
        );
        $tempRequest->setBody(json_encode($data));
        
        // Create temporary response
        $tempResponse = new \CodeIgniter\HTTP\Response(new \Config\App());
        
        // Initialize the controller properly
        $generalController->initController($tempRequest, $tempResponse, service('logger'));
        $generalController->request = $tempRequest;
        $generalController->response = $tempResponse;
        
        return $generalController;
    }

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        // Preload session
        $this->session = service('session');

        // Preload common models
        $this->userModel = new UserModel();
        $this->roomModel = new ChatRoomModel();
        $this->messageModel = new MessageModel();
        $this->participantModel = new RoomParticipantModel();
        $this->connectionModel = new WebSocketConnectionModel();
    }

    /**
     * Check if user is authenticated
     */
    protected function isAuthenticated(): bool
    {
        return (bool) $this->session->get('logged_in');
    }

    /**
     * Get current user data
     */
    protected function getCurrentUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        return [
            'id' => $this->session->get('user_id'),
            'username' => $this->session->get('username'),
            'email' => $this->session->get('email'),
            'session_token' => $this->session->get('session_token')
        ];
    }

    /**
     * Return JSON response
     */
    protected function jsonResponse(array $data, int $statusCode = 200)
    {
        return $this->response->setStatusCode($statusCode)->setJSON($data);
    }

    /**
     * Return error JSON response
     */
    protected function jsonError(string $message, int $statusCode = 400)
    {
        return $this->jsonResponse(['error' => $message], $statusCode);
    }

    /**
     * Return success JSON response
     */
    protected function jsonSuccess(array $data = [], string $message = 'Success')
    {
        return $this->jsonResponse(array_merge(['success' => true, 'message' => $message], $data));
    }

    /**
     * Require authentication - redirect if not logged in
     */
    protected function requireAuth()
    {
        if (!$this->isAuthenticated()) {
            if ($this->request->isAJAX()) {
                return $this->jsonError('Authentication required', 401);
            }
            return redirect()->to('/login');
        }
        return null;
    }

    /**
     * Generate session token using General controller
     */
    protected function generateSessionToken(int $length = 32): string
    {
        // Validate input
        $length = max(16, min(64, $length)); // Ensure length is between 16 and 64
        
        try {
            $generalController = new General();
            
            // Create temporary request
            $tempRequest = new IncomingRequest(
                new \Config\App(),
                new \CodeIgniter\HTTP\URI(),
                json_encode(['length' => $length]),
                new \CodeIgniter\HTTP\UserAgent()
            );
            $tempRequest->setBody(json_encode(['length' => $length]));
            
            // Create temporary response
            $tempResponse = new \CodeIgniter\HTTP\Response(new \Config\App());
            
            // Initialize the controller properly
            $generalController->initController($tempRequest, $tempResponse, service('logger'));
            $generalController->request = $tempRequest;
            $generalController->response = $tempResponse;
            
            $response = $generalController->generateToken();
            $data = json_decode($response->getBody(), true);
            return $data['token'] ?? bin2hex(random_bytes($length));
        } catch (\Exception $e) {
            // Log error and return fallback token
            log_message('error', 'Failed to generate session token: ' . $e->getMessage());
            return bin2hex(random_bytes($length));
        }
    }

    /**
     * Sanitize message using General controller
     */
    protected function sanitizeMessage(string $message): string
    {
        // Validate input
        if (empty($message)) {
            return '';
        }
        
        // Limit message length to prevent abuse
        if (strlen($message) > 1000) {
            $message = substr($message, 0, 1000);
        }
        
        try {
            $generalController = new General();
            
            // Create temporary request
            $tempRequest = new IncomingRequest(
                new \Config\App(),
                new \CodeIgniter\HTTP\URI(),
                json_encode(['message' => $message]),
                new \CodeIgniter\HTTP\UserAgent()
            );
            $tempRequest->setBody(json_encode(['message' => $message]));
            
            // Create temporary response
            $tempResponse = new \CodeIgniter\HTTP\Response(new \Config\App());
            
            // Initialize the controller properly
            $generalController->initController($tempRequest, $tempResponse, service('logger'));
            $generalController->request = $tempRequest;
            $generalController->response = $tempResponse;
            
            $response = $generalController->sanitizeMessage();
            $data = json_decode($response->getBody(), true);
            
            return $data['sanitized_message'] ?? strip_tags(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        } catch (\Exception $e) {
            // Log error and return fallback sanitized message
            log_message('error', 'Failed to sanitize message: ' . $e->getMessage());
            return strip_tags(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        }
    }

    /**
     * Log chat activity using General controller
     */
    protected function logChatActivity(string $action, array $data = []): void
    {
        try {
            $generalController = new General();
            
            // Create temporary request
            $tempRequest = new IncomingRequest(
                new \Config\App(),
                new \CodeIgniter\HTTP\URI(),
                json_encode(['action' => $action, 'data' => $data]),
                new \CodeIgniter\HTTP\UserAgent()
            );
            $tempRequest->setBody(json_encode(['action' => $action, 'data' => $data]));
            
            // Create temporary response
            $tempResponse = new \CodeIgniter\HTTP\Response(new \Config\App());
            
            // Initialize the controller properly
            $generalController->initController($tempRequest, $tempResponse, service('logger'));
            $generalController->request = $tempRequest;
            $generalController->response = $tempResponse;
            
            $generalController->logActivity();
        } catch (\Exception $e) {
            // Log error but don't throw - activity logging should not break the main flow
            log_message('error', 'Failed to log chat activity: ' . $e->getMessage());
        }
    }

    /**
     * Validate room access using General controller
     */
    protected function validateRoomAccess(array $room, int $userId): bool
    {
        try {
            $generalController = new General();
            
            // Create temporary request
            $tempRequest = new IncomingRequest(
                new \Config\App(),
                new \CodeIgniter\HTTP\URI(),
                json_encode(['room' => $room, 'user_id' => $userId]),
                new \CodeIgniter\HTTP\UserAgent()
            );
            $tempRequest->setBody(json_encode(['room' => $room, 'user_id' => $userId]));
            
            // Create temporary response
            $tempResponse = new \CodeIgniter\HTTP\Response(new \Config\App());
            
            // Initialize the controller properly
            $generalController->initController($tempRequest, $tempResponse, service('logger'));
            $generalController->request = $tempRequest;
            $generalController->response = $tempResponse;
            
            $response = $generalController->validateRoomAccess();
            $data = json_decode($response->getBody(), true);
            
            return $data['has_access'] ?? false;
        } catch (\Exception $e) {
            // Log error and return false for security (deny access on error)
            log_message('error', 'Failed to validate room access: ' . $e->getMessage());
            return false;
        }
    }
}