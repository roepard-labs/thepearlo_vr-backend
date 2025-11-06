<?php
/**
 * RUTA: Actualizar Perfil del Usuario
 * ARCHIVO: up_user.php
 * MÉTODO HTTP: PUT
 * ENDPOINT: /routes/profile/up_user.php
 * 
 * PROPÓSITO:
 * - Actualizar información personal del usuario actual
 * - Incluye datos personales, biografía, género y redes sociales
 * - Validación de campos y actualización en dos tablas (users y user_social)
 * 
 * ARQUITECTURA MVC:
 * - RUTA: Punto de entrada con middleware de validación
 * - MIDDLEWARE: Auth y Status para verificación
 * - ACTUALIZACIÓN: users y user_social en transacción
 * 
 * MIDDLEWARE APLICADO:
 * - Auth::checkAuth() - Verifica autenticación válida
 * - Status::checkStatus(1) - Verifica usuario activo
 * 
 * PARÁMETROS JSON (BODY):
 * {
 *   "first_name": "Juan",
 *   "last_name": "Pérez",
 *   "phone": "+56912345678",
 *   "bio": "Nueva biografía",
 *   "gender_id": 2,
 *   "birthdate": "1990-01-15",
 *   "country": "Chile",
 *   "city": "Santiago",
 *   "social": {
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
 *   "message": "Perfil actualizado exitosamente",
 *   "data": {
 *     "user_id": 1,
 *     "updated_fields": ["first_name", "last_name", "bio", "social"]
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

    // ===================================
    // CONECTAR A BASE DE DATOS
    // ===================================

    $dbConfig = new DBConfig();
    $db = $dbConfig->getConnection();

    // Iniciar transacción
    $db->beginTransaction();

    $updatedFields = [];

    // ===================================
    // ACTUALIZAR TABLA USERS
    // ===================================

    $userFields = [];
    $userParams = [':user_id' => $userId];

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
        $stmtCheckSocial->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmtCheckSocial->execute();
        $existingSocial = $stmtCheckSocial->fetch(PDO::FETCH_ASSOC);

        if ($existingSocial) {
            // UPDATE: Actualizar registro existente
            $socialFields = [];
            $socialParams = [':user_id' => $userId];

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
                ':user_id' => $userId,
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
        'message' => 'Perfil actualizado exitosamente',
        'data' => [
            'user_id' => $userId,
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
        'message' => 'Error al actualizar perfil',
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