<?php
/**
 * RUTA: Cierre de Sesión de Usuario
 * ARCHIVO: logout_user.php
 * MÉTODO HTTP: POST/GET (definido en el controlador)
 * ENDPOINT: /api/routes/logout_user.php
 * 
 * PROPÓSITO:
 * - Cerrar la sesión activa del usuario
 * - Invalidar tokens de autenticación
 * - Limpiar datos de sesión del servidor
 * 
 * ARQUITECTURA MVC:
 * - RUTA: Punto de entrada simple sin middleware (logout permite acceso libre)
 * - CONTROLADOR: LogoutController maneja la lógica de presentación
 * - SERVICIO: LogoutService ejecuta la lógica de negocio
 * - MODELO: No requiere modelo específico (usa sesiones PHP)
 * 
 * MIDDLEWARE APLICADO:
 * - Ninguno (el logout debe ser accesible incluso con sesión expirada)
 * 
 * PARÁMETROS: 
 * - Ninguno requerido (usa sesión actual)
 * 
 * RESPUESTA JSON:
 * {
 *   "status": "success|error",
 *   "message": "Mensaje descriptivo"
 * }
 * 
 * CÓDIGOS DE RESPUESTA:
 * - 200: Logout exitoso
 * - 405: Método no permitido (si se implementa validación)
 * - 500: Error interno del servidor
 */

// Aplicar CORS headers
require_once __DIR__ . '/../../config/cors.php';

// Configurar header de respuesta JSON
header('Content-Type: application/json');

// Requiere el controlador para acceder a su clase
require_once __DIR__ . '/../../controllers/LogoutController.php';

try {
    // Crear instancia del controlador y ejecutar logout
    // El controlador se encarga de:
    // 1. Verificar si existe una sesión activa
    // 2. Delegar al servicio de logout para limpiar la sesión
    // 3. Invalidar cookies de sesión si existen
    // 4. Formatear la respuesta JSON de confirmación
    $controller = new LogoutController();
    $controller->handleRequest();
} catch (Exception $e) {
    // Manejo de errores - el logout debe ser robusto
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error interno durante el cierre de sesión'
    ]);
    
    // Log error for debugging
    error_log("Logout Error: " . $e->getMessage());
}
?>