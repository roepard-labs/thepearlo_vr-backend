<?php
/**
 * API Endpoint: Cerrar Todas las Sesiones (excepto la actual)
 * POST /routes/user/close_all_sessions.php
 * Cierra todas las sesiones activas del usuario excepto la actual
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

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Método no permitido. Use POST.'
    ]);
    exit;
}

try {
    $userSession = new UserSession();

    // Obtener sesión actual para no cerrarla
    $currentSessionId = session_id();

    // Cerrar todas las demás sesiones
    $closedCount = $userSession->closeAllUserSessions($userId, $currentSessionId);

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => $closedCount > 0
            ? "Se cerraron {$closedCount} sesión(es) correctamente"
            : 'No hay otras sesiones activas para cerrar',
        'data' => [
            'sessions_closed' => $closedCount,
            'current_session_id' => $currentSessionId
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al cerrar sesiones',
        'error' => $e->getMessage()
    ]);
}
