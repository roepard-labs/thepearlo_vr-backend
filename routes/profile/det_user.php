<?php
/**
 * RUTA: Detalles Completos del Perfil del Usuario
 * ARCHIVO: det_user.php
 * MÉTODO HTTP: GET
 * ENDPOINT: /routes/profile/det_user.php
 * 
 * PROPÓSITO:
 * - Obtener información completa del perfil del usuario actual
 * - Incluye datos personales, biografía, género y redes sociales
 * - Endpoint especializado para la página de perfil
 * 
 * ARQUITECTURA MVC:
 * - RUTA: Punto de entrada con middleware de validación
 * - MIDDLEWARE: Auth y Status para verificación
 * - CONSULTA DIRECTA: JOIN de users, genders y user_social
 * 
 * MIDDLEWARE APLICADO:
 * - Auth::checkAuth() - Verifica autenticación válida
 * - Status::checkStatus(1) - Verifica usuario activo
 * 
 * RESPUESTA JSON:
 * {
 *   "status": "success",
 *   "message": "Perfil del usuario obtenido exitosamente",
 *   "data": {
 *     "user_id": 1,
 *     "username": "juanperez",
 *     "email": "juan@example.com",
 *     "first_name": "Juan",
 *     "last_name": "Pérez",
 *     "full_name": "Juan Pérez",
 *     "phone": "+56912345678",
 *     "bio": "Desarrollador apasionado por la tecnología",
 *     "gender_id": 2,
 *     "gender_name": "Masculino",
 *     "birthdate": "1990-01-15",
 *     "country": "Chile",
 *     "city": "Santiago",
 *     "role_id": 1,
 *     "role_name": "user",
 *     "status_id": 1,
 *     "status_name": "active",
 *     "profile_picture": "user_1.png",
 *     "social": {
 *       "github_username": "juanperez",
 *       "linkedin_username": "juanperez",
 *       "twitter_username": "juanperez",
 *       "discord_tag": "juanperez#1234",
 *       "personal_website": "https://juanperez.dev",
 *       "show_social_public": true
 *     },
 *     "created_at": "2024-01-15 10:30:00",
 *     "updated_at": "2024-11-05 14:20:00"
 *   }
 * }
 */

// Aplicar CORS headers
require_once __DIR__ . '/../../config/cors.php';

// Middleware de autenticación y estado
require_once __DIR__ . '/../../middleware/user.php';
require_once __DIR__ . '/../../middleware/status.php';

// Configuración de base de datos
require_once __DIR__ . '/../../core/db.php';

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
    $userId = $auth->checkAuth(); // Retorna user_id o exit con 401

    // Validar que userId es válido
    if (!$userId || !is_numeric($userId) || $userId <= 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'ID de usuario inválido'
        ]);
        exit;
    }

    // Verificar estado del usuario (retorna true o exit)
    Status::checkStatus(1);

    // ===================================
    // OBTENER DATOS COMPLETOS DEL PERFIL
    // ===================================

    $dbConfig = new DBConfig();
    $db = $dbConfig->getConnection();

    // Query completa con JOIN a genders y user_social
    $sql = "SELECT 
                u.user_id,
                u.username,
                u.email,
                u.first_name,
                u.last_name,
                u.phone,
                u.bio,
                u.gender_id,
                g.gender_name,
                u.birthdate,
                u.country,
                u.city,
                u.role_id,
                u.status_id,
                u.profile_picture,
                u.created_at,
                u.updated_at,
                s.github_username,
                s.linkedin_username,
                s.twitter_username,
                s.discord_tag,
                s.personal_website,
                s.show_social_public
            FROM users u
            LEFT JOIN genders g ON u.gender_id = g.gender_id
            LEFT JOIN user_social s ON u.user_id = s.user_id
            WHERE u.user_id = :user_id";

    $stmt = $db->prepare($sql);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Validar que se encontraron datos
    if (!$userData) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Usuario no encontrado'
        ]);
        exit;
    }

    // ===================================
    // OBTENER ÚLTIMO LOGIN DE SESIONES
    // ===================================

    $sqlLastLogin = "SELECT MAX(created_at) as last_login 
                     FROM user_sessions 
                     WHERE user_id = :user_id";
    $stmtLastLogin = $db->prepare($sqlLastLogin);
    $stmtLastLogin->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmtLastLogin->execute();
    $lastLoginData = $stmtLastLogin->fetch(PDO::FETCH_ASSOC);

    // Agregar last_login a userData
    $userData['last_login'] = $lastLoginData['last_login'] ?? null;

    // ===================================
    // OBTENER NOMBRES DE ROL Y ESTADO
    // ===================================

    $roleNames = [
        1 => 'user',
        2 => 'admin',
        3 => 'supervisor'
    ];

    $statusNames = [
        1 => 'active',
        2 => 'inactive',
        3 => 'suspended',
        4 => 'banned'
    ];

    $roleName = $roleNames[$userData['role_id']] ?? 'unknown';
    $statusName = $statusNames[$userData['status_id']] ?? 'unknown';

    // ===================================
    // PREPARAR RESPUESTA
    // ===================================

    // Nombre completo
    $fullName = trim($userData['first_name'] . ' ' . $userData['last_name']);

    // Datos de redes sociales
    $socialData = [
        'github_username' => $userData['github_username'] ?? null,
        'linkedin_username' => $userData['linkedin_username'] ?? null,
        'twitter_username' => $userData['twitter_username'] ?? null,
        'discord_tag' => $userData['discord_tag'] ?? null,
        'personal_website' => $userData['personal_website'] ?? null,
        'show_social_public' => (bool) ($userData['show_social_public'] ?? false)
    ];

    // Calcular "member_since" para mostrar en frontend
    $createdDate = new DateTime($userData['created_at']);
    $now = new DateTime();
    $memberSinceDays = $createdDate->diff($now)->days;

    // Formatear fecha de creación (ej: "Mayo 2025")
    $monthNames = [
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
    $monthName = $monthNames[(int) $createdDate->format('n')];
    $memberSince = $monthName . ' ' . $createdDate->format('Y');

    $responseData = [
        'user_id' => (int) $userData['user_id'],
        'username' => $userData['username'],
        'email' => $userData['email'],
        'first_name' => $userData['first_name'],
        'last_name' => $userData['last_name'],
        'full_name' => $fullName,
        'phone' => $userData['phone'] ?? null,
        'bio' => $userData['bio'] ?? null,
        'gender_id' => (int) ($userData['gender_id'] ?? 1),
        'gender_name' => $userData['gender_name'] ?? 'Prefiero no decirlo',
        'birthdate' => $userData['birthdate'] ?? null,
        'country' => $userData['country'] ?? null,
        'city' => $userData['city'] ?? null,
        'role_id' => (int) $userData['role_id'],
        'role_name' => $roleName,
        'status_id' => (int) $userData['status_id'],
        'status_name' => $statusName,
        'profile_picture' => $userData['profile_picture'] ?? '/assets/img/default-avatar.png',
        'last_login' => $userData['last_login'] ?? null,
        'member_since' => $memberSince,
        'member_since_days' => $memberSinceDays,
        'social' => $socialData,
        'created_at' => $userData['created_at'],
        'updated_at' => $userData['updated_at']
    ];

    // Respuesta exitosa
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Perfil del usuario obtenido exitosamente',
        'data' => $responseData
    ]);

} catch (PDOException $e) {
    // Error de base de datos
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al obtener datos del perfil',
        'details' => $e->getMessage()
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