<?php
/**
 * RUTA: Detalles de Usuario
 * ARCHIVO: det_user.php
 * MÉTODO HTTP: POST
 * ENDPOINT: /api/routes/det_user.php
 * 
 * PROPÓSITO:
 * - Obtener información detallada de un usuario específico
 * - Permitir consulta de datos de perfil de usuario
 * 
 * ARQUITECTURA MVC:
 * - RUTA: Punto de entrada con middleware de seguridad
 * - CONTROLADOR: DetUserController maneja la lógica de presentación
 * - SERVICIO: UserDetailsService ejecuta la lógica de negocio
 * - MODELO: UserDetails interactúa con la base de datos
 * 
 * MIDDLEWARE APLICADO:
 * - Verificación de autenticación manual (sesión activa)
 * - Status::checkStatus(1) - Usuario debe estar activo
 * 
 * PARÁMETROS POST:
 * - user_id: int (ID del usuario a consultar, opcional si es el mismo usuario)
 * 
 * RESPUESTA JSON:
 * {
 *   "status": "success|error",
 *   "message": "Mensaje descriptivo",
 *   "data": { user_details } // solo en caso exitoso
 * }
 */

// Aplicar CORS headers
require_once __DIR__ . '/../../config/cors.php';

// Configurar header de respuesta JSON
header('Content-Type: application/json');

// Requiere middleware de autenticación y estado
require_once __DIR__ . '/../../middleware/user.php';
require_once __DIR__ . '/../../middleware/status.php';

// Aplicar middleware de seguridad estándar
Auth::requireAuth();  // Verificar autenticación primero
Status::checkStatus(1); // Luego verificar estado activo

// Validar método HTTP - solo acepta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Método no permitido. Use POST.'
    ]);
    exit;
}

// Requiere el controlador para acceder a su clase
require_once __DIR__ . '/../../controllers/DetUserController.php';

try {
    // Crear instancia del controlador y ejecutar operación
    // El controlador se encarga de:
    // 1. Validar parámetros de entrada
    // 2. Verificar permisos para consultar el usuario solicitado
    // 3. Delegar al servicio de detalles de usuario
    // 4. Formatear la respuesta JSON
    $controller = new DetUserController();
    $controller->getUserDetails();
} catch (Throwable $e) {
    // Manejo de errores con logging detallado para debugging
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error interno del servidor'
    ]);
    
    // Log error for debugging (consider implementing proper logging)
    error_log("DetUser Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
}
?>