<?php
/**
 * RUTA: Listado de Usuarios
 * ARCHIVO: list_users.php
 * MÉTODO HTTP: GET/POST (definido en el controlador)
 * ENDPOINT: /api/routes/list_users.php
 * 
 * PROPÓSITO:
 * - Obtener listado de usuarios del sistema
 * - Permitir filtrado y paginación de usuarios
 * - Funcionalidad administrativa para gestión de usuarios
 * 
 * ARQUITECTURA MVC:
 * - RUTA: Punto de entrada con middleware de autenticación
 * - CONTROLADOR: ListUserController maneja la lógica de presentación
 * - SERVICIO: UserListService ejecuta la lógica de negocio
 * - MODELO: UserList interactúa con la base de datos
 * 
 * MIDDLEWARE APLICADO:
 * - Auth::requireAuth() - Verificación estándar de autenticación
 * - Auth::checkAnyRole([1, 2, 3]) - Verificación de roles permitidos
 * 
 * PERMISOS REQUERIDOS:
 * - Usuario autenticado con sesión válida
 * - Usuario con role_id 1, 2 o 3 (roles activos del sistema)
 * 
 * PARÁMETROS (según método):
 * - Filtros de búsqueda (opcional)
 * - Paginación (página, límite)
 * 
 * RESPUESTA JSON:
 * {
 *   "status": "success|error",
 *   "message": "Mensaje descriptivo",
 *   "data": {
 *     "users": [...],
 *     "pagination": {...}
 *   }
 * }
 */

// Aplicar CORS headers
require_once __DIR__ . '/../../config/cors.php';

// Requiere middleware de autenticación
require_once __DIR__ . '/../../middleware/user.php';

// Configurar header de respuesta JSON
header('Content-Type: application/json');

// Validar método HTTP - acepta GET y POST
$allowed_methods = ['GET', 'POST'];
if (!in_array($_SERVER['REQUEST_METHOD'], $allowed_methods)) {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Método no permitido. Use GET o POST.'
    ]);
    exit;
}

// Aplicar middleware de seguridad estándar con verificación de roles
Auth::requireAuth(); // Verificar autenticación
Auth::checkAnyRole([1, 2, 3]); // Permitir a usuarios con role_id 1, 2 o 3 (todos los roles activos)
// Nota: Si se requiere restringir solo a administradores, cambiar por: Auth::checkRole(2);

// Requiere el controlador para acceder a su clase
require_once __DIR__ . '/../../controllers/ListUserController.php';

try {
    // Crear instancia del controlador y ejecutar operación
    // El controlador se encarga de:
    // 1. Determinar el método HTTP apropiado
    // 2. Validar permisos del usuario para listar usuarios
    // 3. Procesar parámetros de filtrado y paginación
    // 4. Delegar al servicio de listado de usuarios
    // 5. Formatear la respuesta JSON con los datos
    $controller = new ListUserController();
    $controller->handleRequest();
} catch (Exception $e) {
    // Manejo de errores
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error interno del servidor'
    ]);
    
    // Log error for debugging
    error_log("ListUsers Error: " . $e->getMessage());
}
?>