<?php
require_once __DIR__ . '/../core/session.php';
// Clase LogoutService
class LogoutService {
    public function logout() {
    ensure_session_started();
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