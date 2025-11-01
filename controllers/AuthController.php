<?php
/**
 * Controlador de Autenticación
 * Maneja HTTP requests, sesiones y coordina servicios
 */

require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../core/session.php';

class AuthController {
    private $authService;
    
    public function __construct() {
        $this->authService = new AuthService();
    }

    /**
     * Maneja el login de usuarios
     * Responsable de HTTP, sesiones y coordinación
     */
    public function login() {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            $this->sendResponse(['status' => 'error', 'message' => 'Método no permitido'], 405);
            return;
        }

        $input = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        try {
            // Validar credenciales usando el servicio
            $result = $this->authService->validateCredentials($input, $password);
            
            // Si las credenciales son válidas, manejar la sesión
            if ($result['status'] === 'success') {
                $this->createUserSession($result['user_data']);
                $result['message'] = 'Login exitoso';
            }
            
            $this->sendResponse($result);
            
        } catch (Exception $e) {
            $this->sendResponse([
                'status' => 'error', 
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Crea la sesión del usuario
     * Responsabilidad del controlador - NO del servicio
     */
    private function createUserSession($userData): void {
        ensure_session_started();
        session_regenerate_once();
        
        $_SESSION['logged_in'] = true;
        
        // Usar el servicio para preparar datos de sesión
        $sessionData = $this->authService->prepareUserSessionData($userData);
        
        foreach ($sessionData as $key => $value) {
            $_SESSION[$key] = $value;
        }
    }

    private function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
?>