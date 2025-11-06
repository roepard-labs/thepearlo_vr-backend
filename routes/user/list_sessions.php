<?php
/**
 * API Endpoint: Listar Sesiones Activas del Usuario
 * GET /routes/user/list_sessions.php
 * HomeLab AR - Roepard Labs
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../middleware/user.php';
require_once __DIR__ . '/../../middleware/status.php';
require_once __DIR__ . '/../../models/UserSession.php';

// Verificar autenticación
Auth::checkAuth();
Status::checkStatus(1);

// Obtener user_id de la sesión
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'No se encontró el ID de usuario en la sesión'
    ]);
    exit;
}

try {
    $userSession = new UserSession();

    // Obtener sesión actual
    $currentSessionId = session_id();

    // Obtener todas las sesiones activas
    $activeSessions = $userSession->getActiveSessions($userId);

    // Marcar la sesión actual
    foreach ($activeSessions as &$session) {
        $session['is_current'] = ($session['session_id'] === $currentSessionId);
    }

    // Estadísticas
    $stats = [
        'total_active' => count($activeSessions),
        'current_session_id' => $currentSessionId
    ];

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Sesiones recuperadas correctamente',
        'data' => [
            'sessions' => $activeSessions,
            'stats' => $stats
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al recuperar sesiones',
        'error' => $e->getMessage()
    ]);
}
