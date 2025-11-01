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
 * 
 * ARQUITECTURA MVC:
 * - RUTA: Punto de entrada con middleware de validación
 * - MIDDLEWARE: user.php y status.php manejan la verificación
 * - VISTA: Respuesta JSON de confirmación
 * 
 * MIDDLEWARE APLICADO:
 * - Auth::checkAuth() - Verifica autenticación válida
 * - Status::checkStatus(1) - Verifica usuario activo
 * 
 * PARÁMETROS: Ninguno (usa sesión actual)
 * 
 * RESPUESTA JSON:
 * - 200: Sesión válida
 * - 401: No autenticado
 * - 403: Usuario inactivo
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
    
    // Si llega hasta aquí, la sesión es válida
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
        'user_data' => $user_data
    ]);
} catch (Exception $e) {
    // Los middleware manejan sus propios errores,
    // este catch es para errores inesperados
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error interno del servidor'
    ]);
}