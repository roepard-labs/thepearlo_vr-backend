<?php
/**
 * RUTA: Autenticación de Usuario
 * ARCHIVO: auth_user.php
 * MÉTODO HTTP: POST
 * ENDPOINT: /api/routes/auth_user.php
 * 
 * PROPÓSITO:
 * - Autenticar usuarios mediante username/email y password
 * - Crear sesión de usuario autenticado
 * 
 * ARQUITECTURA MVC:
 * - RUTA: Punto de entrada que valida el método HTTP
 * - CONTROLADOR: AuthController maneja la lógica de presentación
 * - SERVICIO: AuthService ejecuta la lógica de negocio de autenticación
 * - MODELO: UserAuth interactúa con la base de datos
 * 
 * PARÁMETROS POST:
 * - username: string (nombre de usuario o email)
 * - password: string (contraseña)
 * 
 * RESPUESTA JSON:
 * {
 *   "status": "success|error",
 *   "message": "Mensaje descriptivo",
 *   "data": { user_data } // solo en caso exitoso
 * }
 */

// Aplicar CORS headers
require_once __DIR__ . '/../../config/cors.php';

// Requiere el controlador para acceder a su clase
require_once __DIR__ . '/../../controllers/AuthController.php';

// Configurar header de respuesta
header('Content-Type: application/json');

// Validar método HTTP - solo acepta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Método no permitido. Use POST.'
    ]);
    exit;
}

try {
    // Crear instancia del controlador y ejecutar autenticación
    // El controlador se encarga de:
    // 1. Validar parámetros de entrada
    // 2. Delegar al servicio de autenticación
    // 3. Formatear la respuesta JSON
    $authController = new AuthController();
    $authController->login();
} catch (Exception $e) {
    // Manejo de errores no esperados
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error interno del servidor'
    ]);
}
?>