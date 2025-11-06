<?php
/**
 * API Endpoint: Cerrar Sesión Remota
 * POST /routes/user/close_remote_session.php
 * Permite cerrar una sesión específica desde otro dispositivo
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

// Obtener datos del request
$input = json_decode(file_get_contents('php://input'), true);
$sessionIdToClose = $input['session_id'] ?? null;

if (!$sessionIdToClose) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Se requiere el ID de sesión a cerrar'
    ]);
    exit;
}

try {
    $userSession = new UserSession();

    // Verificar que la sesión pertenece al usuario actual
    $session = $userSession->getSessionById($sessionIdToClose);

    if (!$session) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Sesión no encontrada'
        ]);
        exit;
    }

    if ($session['user_id'] != $userId) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'No tienes permiso para cerrar esta sesión'
        ]);
        exit;
    }

    // Cerrar la sesión
    $success = $userSession->closeSession($sessionIdToClose, $userId, 'remote');

    if ($success) {
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Sesión cerrada correctamente',
            'data' => [
                'closed_session_id' => $sessionIdToClose
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'No se pudo cerrar la sesión'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al cerrar sesión',
        'error' => $e->getMessage()
    ]);
}
