<?php
/**
 * API Endpoint: Historial de Sesiones
 * GET /routes/user/session_history.php
 * Muestra el historial de sesiones del usuario (últimas 30 días)
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

    // Obtener límite de registros (por defecto 20)
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
    $limit = min($limit, 100); // Máximo 100 registros

    // Obtener historial
    $history = $userSession->getSessionHistory($userId, $limit);

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Historial recuperado correctamente',
        'data' => [
            'history' => $history,
            'total' => count($history)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al recuperar historial',
        'error' => $e->getMessage()
    ]);
}
