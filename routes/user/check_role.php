<?php
/**
 * RUTA: Verificación de Rol de Usuario
 * ARCHIVO: check_role.php
 * MÉTODO HTTP: GET
 * ENDPOINT: /api/routes/check_role.php
 * 
 * PROPÓSITO:
 * - Verificar si el usuario tiene una sesión válida y activa
 * - Obtener información del rol del usuario para redirección
 * - Validar permisos y estado de usuario
 * 
 * ARQUITECTURA MVC:
 * - RUTA: Punto de entrada con middleware de validación
 * - MIDDLEWARE: user.php y status.php manejan la verificación
 * - VISTA: Respuesta JSON con información de rol y estado
 * 
 * MIDDLEWARE APLICADO:
 * - Auth::checkAuth() - Verifica autenticación válida
 * - Status::checkStatus(1) - Verifica usuario activo
 * 
 * PARÁMETROS: Ninguno (usa sesión actual)
 * 
 * RESPUESTA JSON:
 * - 200: Sesión válida con información de rol
 * - 401: No autenticado
 * - 403: Usuario inactivo
 * - 405: Método no permitido
 */

// Aplicar CORS headers
require_once __DIR__ . '/../../config/cors.php';

// Requiere middleware de autenticación y estado
require_once __DIR__ . '/../../middleware/user.php';
require_once __DIR__ . '/../../middleware/status.php';
require_once __DIR__ . '/../../models/UserAuth.php';

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
    // Parsear la respuesta de Auth::checkAuth() y complementarla
    $auth_response = json_decode($auth_output, true);
    
    // Obtener información completa del usuario desde la base de datos
    $userModel = new User();
    $userData = $userModel->findById($user_id);
    
    if (!$userData) {
        throw new Exception('Usuario no encontrado en la base de datos');
    }
    
    // Extraer información del usuario desde la BD
    $user_role_id = (int)$userData['role_id']; // Convertir a entero
    $user_first_name = $userData['first_name'] ?? 'Usuario';
    $user_last_name = $userData['last_name'] ?? '';
    $user_name = trim($user_first_name . ' ' . $user_last_name);
    $user_email = $userData['email'] ?? '';
    
    // Definir roles según la base de datos
    $role_names = [
        1 => 'user',
        2 => 'admin', 
        3 => 'supervisor'
    ];
    
    // Obtener el nombre del rol basado en el ID
    $actual_role_name = $role_names[$user_role_id] ?? 'user';
    
    // Solo los admin (role_id = 2) pueden acceder al dashboard
    $is_admin = ($user_role_id === 2);
    $can_access_dashboard = $is_admin; // Solo admin puede acceder
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => $can_access_dashboard 
            ? "Usuario admin: acceso autorizado al dashboard (Role ID: $user_role_id)"
            : "Usuario sin permisos de administrador (Role: $actual_role_name, ID: $user_role_id)",
        'logged' => true,
        'user_id' => $user_id,
        'role_id' => $user_role_id,
        'role_name' => $actual_role_name,
        'user_name' => $user_name,
        'user_email' => $user_email,
        'is_admin' => $is_admin,
        'can_access_dashboard' => $can_access_dashboard,
        'debug' => [
            'session_data' => [
                'session_role_id' => $_SESSION['role_id'] ?? 'undefined',
                'session_role' => $_SESSION['role'] ?? 'undefined',
                'session_first_name' => $_SESSION['first_name'] ?? 'undefined'
            ],
            'db_data' => [
                'db_role_id' => $user_role_id,
                'db_role_name' => $actual_role_name,
                'db_user_name' => $user_name
            ]
        ]
    ]);
} catch (Exception $e) {
    // Los middleware manejan sus propios errores,
    // este catch es para errores inesperados
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error interno del servidor',
        'logged' => false
    ]);
}

