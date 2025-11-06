<?php
/**
 * RUTA: Actualizar Usuario (Admin)
 * ARCHIVO: up_user.php
 * MÉTODO HTTP: PUT
 * ENDPOINT: /routes/admin/up_user.php
 * 
 * PROPÓSITO:
 * - Actualizar información completa de cualquier usuario (solo admin)
 * - Incluye datos personales, biografía, género, rol, estado y redes sociales
 * - Validación de permisos y actualización en múltiples tablas
 * 
 * ARQUITECTURA MVC:
 * - RUTA: Punto de entrada con middleware de validación
 * - MIDDLEWARE: Auth (checkAuth) + Role (solo admin role_id=2)
 * - ACTUALIZACIÓN: users y user_social en transacción
 * 
 * MIDDLEWARE APLICADO:
 * - Auth::checkAuth() - Verifica autenticación válida
 * - Auth::checkRole(2) - Solo administradores (role_id = 2)
 * 
 * PARÁMETROS JSON (BODY):
 * {
 *   "user_id": 1,                    // REQUERIDO: ID del usuario a actualizar
 *   "first_name": "Juan",            // Opcional
 *   "last_name": "Pérez",            // Opcional
 *   "email": "juan@example.com",     // Opcional
 *   "phone": "+56912345678",         // Opcional
 *   "bio": "Nueva biografía",        // Opcional (máx 255 caracteres)
 *   "gender_id": 2,                  // Opcional (1-4)
 *   "birthdate": "1990-01-15",       // Opcional
 *   "country": "Chile",              // Opcional
 *   "city": "Santiago",              // Opcional
 *   "role_id": 1,                    // Opcional (1=user, 2=admin, 3=supervisor)
 *   "status_id": 1,                  // Opcional (1=active, 2=inactive, 3=suspended, 4=banned)
 *   "social": {                      // Opcional
 *     "github_username": "juanperez",
 *     "linkedin_username": "juanperez",
 *     "twitter_username": "juanperez",
 *     "discord_tag": "juanperez#1234",
 *     "personal_website": "https://juanperez.dev",
 *     "show_social_public": true
 *   }
 * }
 * 
 * RESPUESTA JSON:
 * {
 *   "status": "success",
 *   "message": "Usuario actualizado exitosamente",
 *   "data": {
 *     "user_id": 1,
 *     "updated_fields": ["first_name", "last_name", "bio", "role_id", "social"],
 *     "total_updates": 5
 *   }
 * }
 */

// Aplicar CORS headers
require_once __DIR__ . '/../../config/cors.php';

// Middleware de autenticación y roles
require_once __DIR__ . '/../../middleware/user.php';
require_once __DIR__ . '/../../middleware/status.php';

// Configuración de base de datos
require_once __DIR__ . '/../../core/db.php';

// Configurar header de respuesta
header('Content-Type: application/json');

// Validar método HTTP - solo acepta PUT
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Método no permitido. Use PUT.'
    ]);
    exit;
}

try {
    // ===================================
    // MIDDLEWARE: Verificar autenticación y rol admin
    // ===================================

    $auth = new Auth();
    $adminUserId = $auth->checkAuth(); // Retorna user_id del admin o exit con 401

    // Verificar que el usuario autenticado es administrador (role_id = 2)
    Auth::checkRole(2); // Solo administradores pueden actualizar usuarios

    // Validar que adminUserId es válido
    if (!$adminUserId || !is_numeric($adminUserId) || $adminUserId <= 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'ID de administrador inválido'
        ]);
        exit;
    }

    // ===================================
    // OBTENER Y VALIDAR DATOS DEL BODY
    // ===================================

    $inputData = json_decode(file_get_contents('php://input'), true);

    if (!$inputData) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Datos JSON inválidos o vacíos'
        ]);
        exit;
    }

    // Validar que se proporcionó user_id del usuario a actualizar
    if (!isset($inputData['user_id']) || empty($inputData['user_id'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'user_id es requerido'
        ]);
        exit;
    }

    $targetUserId = (int) $inputData['user_id'];

    // Validar que el usuario a actualizar existe
    $dbConfig = new DBConfig();
    $db = $dbConfig->getConnection();

    $sqlCheckUser = "SELECT user_id FROM users WHERE user_id = :user_id";
    $stmtCheckUser = $db->prepare($sqlCheckUser);
    $stmtCheckUser->bindParam(':user_id', $targetUserId, PDO::PARAM_INT);
    $stmtCheckUser->execute();

    if (!$stmtCheckUser->fetch()) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Usuario no encontrado',
            'user_id' => $targetUserId
        ]);
        exit;
    }

    // ===================================
    // INICIAR TRANSACCIÓN
    // ===================================

    $db->beginTransaction();

    $updatedFields = [];

    // ===================================
    // ACTUALIZAR TABLA USERS
    // ===================================

    $userFields = [];
    $userParams = [':user_id' => $targetUserId];

    // Validar y agregar campos de users
    if (isset($inputData['first_name']) && !empty($inputData['first_name'])) {
        $userFields[] = "first_name = :first_name";
        $userParams[':first_name'] = trim($inputData['first_name']);
        $updatedFields[] = 'first_name';
    }

    if (isset($inputData['last_name']) && !empty($inputData['last_name'])) {
        $userFields[] = "last_name = :last_name";
        $userParams[':last_name'] = trim($inputData['last_name']);
        $updatedFields[] = 'last_name';
    }

    if (isset($inputData['email']) && !empty($inputData['email'])) {
        // Validar formato de email
        if (!filter_var($inputData['email'], FILTER_VALIDATE_EMAIL)) {
            $db->rollBack();
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Formato de email inválido'
            ]);
            exit;
        }

        // Verificar que el email no esté en uso por otro usuario
        $sqlCheckEmail = "SELECT user_id FROM users WHERE email = :email AND user_id != :user_id";
        $stmtCheckEmail = $db->prepare($sqlCheckEmail);
        $stmtCheckEmail->execute([
            ':email' => trim($inputData['email']),
            ':user_id' => $targetUserId
        ]);

        if ($stmtCheckEmail->fetch()) {
            $db->rollBack();
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'El email ya está en uso por otro usuario'
            ]);
            exit;
        }

        $userFields[] = "email = :email";
        $userParams[':email'] = trim($inputData['email']);
        $updatedFields[] = 'email';
    }

    if (isset($inputData['username']) && !empty($inputData['username'])) {
        // Verificar que el username no esté en uso por otro usuario
        $sqlCheckUsername = "SELECT user_id FROM users WHERE username = :username AND user_id != :user_id";
        $stmtCheckUsername = $db->prepare($sqlCheckUsername);
        $stmtCheckUsername->execute([
            ':username' => trim($inputData['username']),
            ':user_id' => $targetUserId
        ]);

        if ($stmtCheckUsername->fetch()) {
            $db->rollBack();
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'El username ya está en uso por otro usuario'
            ]);
            exit;
        }

        $userFields[] = "username = :username";
        $userParams[':username'] = trim($inputData['username']);
        $updatedFields[] = 'username';
    }

    if (isset($inputData['phone'])) {
        $userFields[] = "phone = :phone";
        $userParams[':phone'] = trim($inputData['phone']);
        $updatedFields[] = 'phone';
    }

    if (isset($inputData['bio'])) {
        // Validar longitud máxima de 255 caracteres
        $bio = trim($inputData['bio']);
        if (strlen($bio) > 255) {
            $db->rollBack();
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'La biografía no puede exceder 255 caracteres',
                'current_length' => strlen($bio)
            ]);
            exit;
        }
        $userFields[] = "bio = :bio";
        $userParams[':bio'] = $bio;
        $updatedFields[] = 'bio';
    }

    if (isset($inputData['gender_id'])) {
        // Validar que gender_id esté entre 1 y 4
        $genderId = (int) $inputData['gender_id'];
        if ($genderId < 1 || $genderId > 4) {
            $db->rollBack();
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'ID de género inválido. Debe ser entre 1 y 4.'
            ]);
            exit;
        }
        $userFields[] = "gender_id = :gender_id";
        $userParams[':gender_id'] = $genderId;
        $updatedFields[] = 'gender_id';
    }

    if (isset($inputData['birthdate'])) {
        $userFields[] = "birthdate = :birthdate";
        $userParams[':birthdate'] = $inputData['birthdate'];
        $updatedFields[] = 'birthdate';
    }

    if (isset($inputData['country'])) {
        $userFields[] = "country = :country";
        $userParams[':country'] = trim($inputData['country']);
        $updatedFields[] = 'country';
    }

    if (isset($inputData['city'])) {
        $userFields[] = "city = :city";
        $userParams[':city'] = trim($inputData['city']);
        $updatedFields[] = 'city';
    }

    // Admin puede cambiar rol del usuario
    if (isset($inputData['role_id'])) {
        // Validar que role_id esté entre 1 y 3
        $roleId = (int) $inputData['role_id'];
        if ($roleId < 1 || $roleId > 3) {
            $db->rollBack();
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'ID de rol inválido. Debe ser entre 1 y 3 (1=user, 2=admin, 3=supervisor).'
            ]);
            exit;
        }
        $userFields[] = "role_id = :role_id";
        $userParams[':role_id'] = $roleId;
        $updatedFields[] = 'role_id';
    }

    // Admin puede cambiar estado del usuario
    if (isset($inputData['status_id'])) {
        // Validar que status_id esté entre 1 y 4
        $statusId = (int) $inputData['status_id'];
        if ($statusId < 1 || $statusId > 4) {
            $db->rollBack();
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'ID de estado inválido. Debe ser entre 1 y 4 (1=active, 2=inactive, 3=suspended, 4=banned).'
            ]);
            exit;
        }
        $userFields[] = "status_id = :status_id";
        $userParams[':status_id'] = $statusId;
        $updatedFields[] = 'status_id';
    }

    // Ejecutar actualización de users si hay campos
    if (!empty($userFields)) {
        $sqlUsers = "UPDATE users SET " . implode(', ', $userFields) . ", updated_at = NOW() WHERE user_id = :user_id";
        $stmtUsers = $db->prepare($sqlUsers);
        $stmtUsers->execute($userParams);
    }

    // ===================================
    // ACTUALIZAR TABLA USER_SOCIAL
    // ===================================

    if (isset($inputData['social']) && is_array($inputData['social'])) {
        $socialData = $inputData['social'];

        // Verificar si el usuario ya tiene registro en user_social
        $sqlCheckSocial = "SELECT social_id FROM user_social WHERE user_id = :user_id";
        $stmtCheckSocial = $db->prepare($sqlCheckSocial);
        $stmtCheckSocial->bindParam(':user_id', $targetUserId, PDO::PARAM_INT);
        $stmtCheckSocial->execute();
        $existingSocial = $stmtCheckSocial->fetch(PDO::FETCH_ASSOC);

        if ($existingSocial) {
            // UPDATE: Actualizar registro existente
            $socialFields = [];
            $socialParams = [':user_id' => $targetUserId];

            if (isset($socialData['github_username'])) {
                $socialFields[] = "github_username = :github_username";
                $socialParams[':github_username'] = trim($socialData['github_username']) ?: null;
            }

            if (isset($socialData['linkedin_username'])) {
                $socialFields[] = "linkedin_username = :linkedin_username";
                $socialParams[':linkedin_username'] = trim($socialData['linkedin_username']) ?: null;
            }

            if (isset($socialData['twitter_username'])) {
                $socialFields[] = "twitter_username = :twitter_username";
                $socialParams[':twitter_username'] = trim($socialData['twitter_username']) ?: null;
            }

            if (isset($socialData['discord_tag'])) {
                $socialFields[] = "discord_tag = :discord_tag";
                $socialParams[':discord_tag'] = trim($socialData['discord_tag']) ?: null;
            }

            if (isset($socialData['personal_website'])) {
                $socialFields[] = "personal_website = :personal_website";
                $socialParams[':personal_website'] = trim($socialData['personal_website']) ?: null;
            }

            if (isset($socialData['show_social_public'])) {
                $socialFields[] = "show_social_public = :show_social_public";
                $socialParams[':show_social_public'] = $socialData['show_social_public'] ? 1 : 0;
            }

            if (!empty($socialFields)) {
                $sqlUpdateSocial = "UPDATE user_social SET " . implode(', ', $socialFields) . ", updated_at = NOW() WHERE user_id = :user_id";
                $stmtUpdateSocial = $db->prepare($sqlUpdateSocial);
                $stmtUpdateSocial->execute($socialParams);
                $updatedFields[] = 'social';
            }
        } else {
            // INSERT: Crear nuevo registro
            $sqlInsertSocial = "INSERT INTO user_social 
                                (user_id, github_username, linkedin_username, twitter_username, discord_tag, personal_website, show_social_public)
                                VALUES 
                                (:user_id, :github, :linkedin, :twitter, :discord, :website, :show_public)";

            $stmtInsertSocial = $db->prepare($sqlInsertSocial);
            $stmtInsertSocial->execute([
                ':user_id' => $targetUserId,
                ':github' => trim($socialData['github_username'] ?? '') ?: null,
                ':linkedin' => trim($socialData['linkedin_username'] ?? '') ?: null,
                ':twitter' => trim($socialData['twitter_username'] ?? '') ?: null,
                ':discord' => trim($socialData['discord_tag'] ?? '') ?: null,
                ':website' => trim($socialData['personal_website'] ?? '') ?: null,
                ':show_public' => ($socialData['show_social_public'] ?? false) ? 1 : 0
            ]);
            $updatedFields[] = 'social';
        }
    }

    // Confirmar transacción
    $db->commit();

    // ===================================
    // RESPUESTA EXITOSA
    // ===================================

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Usuario actualizado exitosamente',
        'data' => [
            'user_id' => $targetUserId,
            'updated_by_admin_id' => $adminUserId,
            'updated_fields' => $updatedFields,
            'total_updates' => count($updatedFields)
        ]
    ]);

} catch (PDOException $e) {
    // Revertir transacción en caso de error
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    // Error de base de datos
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al actualizar usuario',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    // Error del servidor
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error interno del servidor',
        'details' => $e->getMessage()
    ]);
}
?>