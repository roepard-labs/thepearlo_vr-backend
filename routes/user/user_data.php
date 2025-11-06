<?php
/**
 * RUTA: Datos Completos del Usuario
 * ARCHIVO: user_data.php
 * MÉTODO HTTP: GET
 * ENDPOINT: /routes/user/user_data.php
 * 
 * PROPÓSITO:
 * - Obtener información completa del usuario actual
 * - Incluye datos personales, estadísticas y fechas importantes
 * - last_login se obtiene desde user_sessions.last_activity (sesión más reciente activa)
 * 
 * ARQUITECTURA MVC:
 * - RUTA: Punto de entrada con middleware de validación
 * - MIDDLEWARE: user.php y status.php manejan la verificación
 * - MODELO: UserAuth obtiene datos del usuario
 * - CONSULTA DIRECTA: user_sessions para obtener last_activity
 * 
 * MIDDLEWARE APLICADO:
 * - Auth::checkAuth() - Verifica autenticación válida
 * - Status::checkStatus(1) - Verifica usuario activo
 * 
 * PARÁMETROS: Ninguno (usa sesión actual)
 * 
 * RESPUESTA JSON:
 * {
 *   "status": "success",
 *   "message": "Datos del usuario obtenidos exitosamente",
 *   "data": {
 *     "user_id": 1,
 *     "username": "juanperez",
 *     "email": "juan@example.com",
 *     "first_name": "Juan",
 *     "last_name": "Pérez",
 *     "phone": "+56912345678",
 *     "role_id": 1,
 *     "role_name": "user",
 *     "status_id": 1,
 *     "status_name": "active",
 *     "created_at": "2024-01-15 10:30:00",
 *     "updated_at": "2024-11-05 14:20:00",
 *     "last_login": "2024-11-05 14:20:00",  // Desde user_sessions.last_activity
 *     "full_name": "Juan Pérez",
 *     "member_since": "Enero 2024",
 *     "member_since_days": 294
 *   }
 * }
 */

// Aplicar CORS headers
require_once __DIR__ . '/../../config/cors.php';

// Requiere middleware de autenticación y estado
require_once __DIR__ . '/../../middleware/user.php';
require_once __DIR__ . '/../../middleware/status.php';

// Requiere el modelo de usuario
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
    // ===================================
    // MIDDLEWARE: Verificar autenticación y estado
    // ===================================

    $auth = new Auth();
    // NOTA: checkAuth() retorna directamente el user_id (int) o hace exit() con 401
    $userId = $auth->checkAuth();

    // Validar que userId es válido
    if (!$userId || !is_numeric($userId) || $userId <= 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'ID de usuario inválido',
            'debug' => [
                'user_id' => $userId,
                'tipo' => gettype($userId)
            ]
        ]);
        exit;
    }

    // Verificar estado del usuario (retorna true o exit)
    Status::checkStatus(1);

    // ===================================
    // OBTENER DATOS DEL USUARIO
    // ===================================

    $userModel = new User();
    $userData = $userModel->findById($userId);

    // Validar que userData es un array válido
    if (!$userData || !is_array($userData) || empty($userData)) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Usuario no encontrado',
            'debug' => [
                'user_id_buscado' => $userId,
                'resultado_tipo' => gettype($userData),
                'resultado_valor' => $userData
            ]
        ]);
        exit;
    }

    // Validar que los campos requeridos existan
    if (!isset($userData['role_id']) || !isset($userData['status_id'])) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Datos de usuario incompletos',
            'debug' => [
                'campos_disponibles' => array_keys($userData)
            ]
        ]);
        exit;
    }

    // ===================================
    // OBTENER LAST LOGIN y GÉNERO desde BD
    // ===================================

    try {
        require_once __DIR__ . '/../../core/db.php';
        $dbConfig = new DBConfig();
        $db = $dbConfig->getConnection();

        // Obtener la última actividad de sesiones activas
        $sqlLastActivity = "SELECT last_activity 
                            FROM user_sessions 
                            WHERE user_id = :user_id 
                            AND is_active = 1 
                            ORDER BY last_activity DESC 
                            LIMIT 1";

        $stmtLastActivity = $db->prepare($sqlLastActivity);
        $stmtLastActivity->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmtLastActivity->execute();

        $lastActivityData = $stmtLastActivity->fetch(PDO::FETCH_ASSOC);
        $lastLogin = $lastActivityData ? $lastActivityData['last_activity'] : null;

        // Obtener nombre del género
        $sqlGender = "SELECT g.gender_name 
                      FROM users u 
                      LEFT JOIN genders g ON u.gender_id = g.gender_id 
                      WHERE u.user_id = :user_id";

        $stmtGender = $db->prepare($sqlGender);
        $stmtGender->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmtGender->execute();

        $genderData = $stmtGender->fetch(PDO::FETCH_ASSOC);
        $genderName = $genderData ? $genderData['gender_name'] : 'Prefiero no decirlo';

    } catch (PDOException $e) {
        // Si hay error, usar valores por defecto
        $lastLogin = null;
        $genderName = 'Prefiero no decirlo';
    }

    // ===================================
    // OBTENER NOMBRE DEL ROL
    // ===================================

    $roleNames = [
        1 => 'user',
        2 => 'admin',
        3 => 'supervisor'
    ];

    $roleName = $roleNames[$userData['role_id']] ?? 'unknown';

    // ===================================
    // OBTENER NOMBRE DEL ESTADO
    // ===================================

    $statusNames = [
        1 => 'active',
        2 => 'inactive',
        3 => 'suspended',
        4 => 'banned'
    ];

    $statusName = $statusNames[$userData['status_id']] ?? 'unknown';

    // ===================================
    // CALCULAR INFORMACIÓN ADICIONAL
    // ===================================

    // Nombre completo
    $firstName = $userData['first_name'] ?? '';
    $lastName = $userData['last_name'] ?? '';
    $fullName = trim($firstName . ' ' . $lastName);

    // Fecha de "miembro desde" en español
    try {
        $createdDate = new DateTime($userData['created_at']);

        // Usar array de meses en español (strftime está deprecado en PHP 8.1+)
        $mesesEspanol = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre'
        ];

        $mes = (int) $createdDate->format('n');
        $anio = $createdDate->format('Y');
        $memberSince = $mesesEspanol[$mes] . ' ' . $anio;

        // Días como miembro
        $today = new DateTime();
        $memberSinceDays = $createdDate->diff($today)->days;
    } catch (Exception $e) {
        // Si hay error con las fechas, usar valores por defecto
        $memberSince = 'Fecha no disponible';
        $memberSinceDays = 0;
    }

    // ===================================
    // PREPARAR RESPUESTA
    // ===================================

    $responseData = [
        'user_id' => (int) $userData['user_id'],
        'username' => $userData['username'],
        'email' => $userData['email'],
        'first_name' => $userData['first_name'],
        'last_name' => $userData['last_name'],
        'phone' => $userData['phone'] ?? null,
        'bio' => $userData['bio'] ?? null, // Biografía del usuario
        'gender_id' => (int) ($userData['gender_id'] ?? 1), // ID del género
        'gender_name' => $genderName, // Nombre del género
        'role_id' => (int) $userData['role_id'],
        'role_name' => $roleName,
        'status_id' => (int) $userData['status_id'],
        'status_name' => $statusName,
        'created_at' => $userData['created_at'],
        'updated_at' => $userData['updated_at'],
        'last_login' => $lastLogin, // Desde user_sessions.last_activity
        'full_name' => $fullName,
        'member_since' => $memberSince,
        'member_since_days' => $memberSinceDays
    ];

    // Respuesta exitosa
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Datos del usuario obtenidos exitosamente',
        'data' => $responseData
    ]);

} catch (Exception $e) {
    // Error del servidor
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error interno del servidor',
        'details' => $e->getMessage()
    ]);
}
?>