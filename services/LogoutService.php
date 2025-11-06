<?php
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../models/UserSession.php';

// Clase LogoutService
class LogoutService
{
    private $sessionModel;

    public function __construct()
    {
        $this->sessionModel = new UserSession();
    }

    public function logout()
    {
        ensure_session_started();

        // CRÍTICO: Obtener session_id ANTES de destruir la sesión
        $currentSessionId = session_id();
        $userId = $_SESSION['user_id'] ?? null;

        // 1. Cerrar sesión en la base de datos
        if ($currentSessionId && $userId) {
            $this->sessionModel->closeSession($currentSessionId, $userId, 'logout');
        }

        // 2. Destruir sesión de PHP
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_unset(); // redundante pero explícito
        session_destroy();

        return true;
    }
}
?>