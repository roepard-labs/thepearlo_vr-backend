<?php
/**
 * RUTA: Registro de Nuevo Usuario
 * ARCHIVO: reg_user.php
 * MÉTODO HTTP: POST
 * ENDPOINT: /api/routes/reg_user.php
 * 
 * PROPÓSITO:
 * - Registrar nuevos usuarios en el sistema
 * - Validar datos de registro y crear cuenta de usuario
 * - Inicializar configuración de usuario por defecto
 * 
 * ARQUITECTURA MVC:
 * - RUTA: Punto de entrada público (no requiere autenticación previa)
 * - CONTROLADOR: RegisterController maneja la lógica de presentación
 * - SERVICIO: RegisterService ejecuta la lógica de negocio de registro
 * - MODELO: UserRegister interactúa con la base de datos
 * 
 * MIDDLEWARE APLICADO:
 * - Ninguno (registro es proceso público)
 * - TODO: Implementar rate limiting para prevenir spam
 * - TODO: Implementar CAPTCHA para registros automatizados
 * 
 * PARÁMETROS POST REQUERIDOS:
 * - username: string (nombre de usuario único)
 * - email: string (email válido y único)
 * - password: string (contraseña con requisitos de seguridad)
 * - password_confirm: string (confirmación de contraseña)
 * 
 * PARÁMETROS POST OPCIONALES:
 * - first_name: string
 * - last_name: string
 * - profile_data: object (datos adicionales de perfil)
 * 
 * RESPUESTA JSON:
 * {
 *   "status": "success|error",
 *   "message": "Mensaje descriptivo",
 *   "data": {
 *     "user_id": int,
 *     "username": string
 *   } // solo en caso exitoso
 * }
 * 
 * CÓDIGOS DE RESPUESTA:
 * - 201: Usuario creado exitosamente
 * - 400: Datos de entrada inválidos
 * - 409: Usuario/email ya existe
 * - 405: Método no permitido
 * - 500: Error interno del servidor
 */

// Aplicar CORS headers
require_once __DIR__ . '/../../config/cors.php';

// Configurar header de respuesta JSON
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

// Requiere el controlador para acceder a su clase
require_once __DIR__ . '/../../controllers/RegisterController.php';

try {
    // Crear instancia del controlador y ejecutar registro
    // El controlador se encarga de:
    // 1. Validar y sanitizar datos de entrada
    // 2. Verificar unicidad de username y email
    // 3. Validar fortaleza de contraseña
    // 4. Delegar al servicio de registro para crear usuario
    // 5. Formatear respuesta JSON con datos del nuevo usuario
    $registerController = new RegisterController();
    $registerController->register();
} catch (Exception $e) {
    // Manejo de errores durante el registro
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error interno durante el registro'
    ]);
    
    // Log error for debugging (sin exponer datos sensibles)
    error_log("Registration Error: " . $e->getMessage());
}