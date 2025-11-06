<?php
/**
 * RUTA: Verificación de Sesión
 * ARCHIVO: check_session.php
 * MÉTODO HTTP: GET
 * ENDPOINT: /api/routes/check_session.php
 * 
 * PROPÓSITO:
 * - Verificar si el usuario tiene una sesión válida y activa
 * - Validar estado de autenticación sin realizar login
 * - Verificar en BD si la sesión está activa (user_sessions.is_active = 1)
 * - Verificar estado del usuario (users.status_id = 1)
 * - Cerrar sesión automáticamente si is_active = 0
 * 
 * ARQUITECTURA MVC:
 * - RUTA: Punto de entrada con middleware de validación
 * - MIDDLEWARE: user.php y status.php manejan la verificación
 * - CONSULTA DIRECTA: user_sessions para verificar is_active
 * - VISTA: Respuesta JSON de confirmación
 * 
 * MIDDLEWARE APLICADO:
 * - Auth::checkAuth() - Verifica autenticación válida
 * - Status::checkStatus(1) - Verifica usuario activo
 * 
 * PARÁMETROS: Ninguno (usa sesión actual)
 * 
 * RESPUESTA JSON:
 * - 200: Sesión válida y activa
 *   {
 *     "status": "success",
 *     "message": "Sesión válida y usuario activo",
 *     "logged": true,
 *     "session_active": true,
 *     "user_active": true,
 *     "user_data": {...}
 *   }
 * 
 * - 401: Sesión cerrada remotamente (is_active = 0)
 *   {
 *     "status": "error",
 *     "message": "Sesión cerrada remotamente",
 *     "logged": false,
 *     "session_active": false,
 *     "user_active": true/false,
 *     "action_required": "logout"
 *   }
 * 
 * - 403: Usuario inactivo/suspendido/baneado
 *   {
 *     "status": "error",
 *     "message": "Usuario inactivo o suspendido",
 *     "logged": false,
 *     "session_active": true,
 *     "user_active": false,
 *     "action_required": "logout"
 *   }
 * 
 * - 405: Método no permitido
 */

// Aplicar CORS headers
require_once __DIR__ . '/../../config/cors.php';

// Requiere middleware de autenticación y estado
require_once __DIR__ . '/../../middleware/user.php';
require_once __DIR__ . '/../../middleware/status.php';

// Configurar header de respuesta
header('Content-Type: application/json');

// Validar método HTTP - solo acepta GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Método no permitido. Use GET.'
    ]);
    exit;
}

try {
    // Aplicar middleware de seguridad
    // 1. Verificar que el usuario esté autenticado
    // Nota: Auth::checkAuth() envía su propia respuesta JSON, pero necesitamos capturarla
    ob_start(); // Capturar la salida de Auth::checkAuth()
    $user_id = Auth::checkAuth();
    $auth_output = ob_get_clean(); // Limpiar el buffer de salida

    // 2. Verificar que el usuario esté activo (status = 1)
    Status::checkStatus(1);

    // ===================================
    // VERIFICAR ESTADO DE LA SESIÓN EN user_sessions
    // ===================================

    require_once __DIR__ . '/../../core/db.php';
    $dbConfig = new DBConfig();
    $db = $dbConfig->getConnection();

    // Obtener el session_id de PHP
    $phpSessionId = session_id();

    // Verificar si la sesión está activa en la base de datos
    $sqlCheckSession = "SELECT is_active, status_id 
                        FROM user_sessions us
                        INNER JOIN users u ON us.user_id = u.user_id
                        WHERE us.session_id = :session_id 
                        AND us.user_id = :user_id
                        LIMIT 1";

    $stmtCheckSession = $db->prepare($sqlCheckSession);
    $stmtCheckSession->bindParam(':session_id', $phpSessionId, PDO::PARAM_STR);
    $stmtCheckSession->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmtCheckSession->execute();

    $sessionData = $stmtCheckSession->fetch(PDO::FETCH_ASSOC);

    // Verificar si la sesión existe y está activa
    $sessionActive = false;
    $userActive = false;

    if ($sessionData) {
        $sessionActive = (int) $sessionData['is_active'] === 1;
        $userActive = (int) $sessionData['status_id'] === 1;
    }

    // Si la sesión NO está activa en BD, cerrar sesión inmediatamente
    if (!$sessionActive) {
        // Destruir sesión PHP
        session_unset();
        session_destroy();

        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Sesión cerrada remotamente',
            'logged' => false,
            'session_active' => false,
            'user_active' => $userActive,
            'action_required' => 'logout' // Frontend debe cerrar sesión
        ]);
        exit;
    }

    // Si el usuario NO está activo (suspendido, baneado, etc.)
    if (!$userActive) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Usuario inactivo o suspendido',
            'logged' => false,
            'session_active' => $sessionActive,
            'user_active' => false,
            'action_required' => 'logout'
        ]);
        exit;
    }

    // Si llega hasta aquí, la sesión es válida Y está activa en BD
    // Obtener datos completos del usuario desde la sesión
    $user_data = [
        'user_id' => $_SESSION['user_id'] ?? $user_id,
        'first_name' => $_SESSION['first_name'] ?? '',
        'last_name' => $_SESSION['last_name'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role_id' => $_SESSION['role_id'] ?? 1
    ];

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Sesión válida y usuario activo',
        'logged' => true,
        'session_active' => true,
        'user_active' => true,
        'user_data' => $user_data
    ]);
} catch (Exception $e) {
    // Los middleware manejan sus propios errores,
    // este catch es para errores inesperados
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error interno del servidor',
        'details' => $e->getMessage()
    ]);
}