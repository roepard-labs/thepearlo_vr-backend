<?php
/**
 * RUTA: Crear Usuario
 * ARCHIVO: cr_user.php
 * MÉTODO HTTP: POST
 * ENDPOINT: /api/routes/cr_user.php
 *
 * Crea un usuario nuevo (solo administradores)
 */

// Aplicar CORS headers
require_once __DIR__ . '/../../config/cors.php';

// Responder JSON
header('Content-Type: application/json');

// Middlewares
require_once __DIR__ . '/../../middleware/user.php';
require_once __DIR__ . '/../../middleware/status.php';

// DB
require_once __DIR__ . '/../../core/db.php';

// Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Método no permitido. Use POST.'
    ]);
    exit;
}

try {
    // Verificar autenticación y rol (solo admin)
    Auth::requireAuth();
    Auth::checkRole(2); // role_id = 2 -> admin

    // Leer JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !is_array($input)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Datos inválidos o vacíos']);
        exit;
    }

    // Campos requeridos
    $required = ['first_name', 'last_name', 'username', 'email', 'password'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => "$field es requerido"]);
            exit;
        }
    }

    $firstName = trim($input['first_name']);
    $lastName = trim($input['last_name']);
    $username = trim($input['username']);
    $email = trim($input['email']);
    $password = $input['password'];

    // Validaciones básicas
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Email inválido']);
        exit;
    }
    if (strlen($username) < 3) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Username debe tener al menos 3 caracteres']);
        exit;
    }
    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Password debe tener al menos 8 caracteres']);
        exit;
    }

    // Conexión DB
    $dbConfig = new DBConfig();
    $db = $dbConfig->getConnection();

    // Verificar unicidad email y username
    $sqlEmail = "SELECT user_id FROM users WHERE email = :email";
    $stmtEmail = $db->prepare($sqlEmail);
    $stmtEmail->execute([':email' => $email]);
    if ($stmtEmail->fetch()) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'El email ya está en uso']);
        exit;
    }

    $sqlUser = "SELECT user_id FROM users WHERE username = :username";
    $stmtUser = $db->prepare($sqlUser);
    $stmtUser->execute([':username' => $username]);
    if ($stmtUser->fetch()) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'El username ya está en uso']);
        exit;
    }

    // Transaction
    $db->beginTransaction();

    // Preparar insert en users
    $now = date('Y-m-d H:i:s');
    $hashed = password_hash($password, PASSWORD_BCRYPT);

    // Campos opcionales
    $phone = isset($input['phone']) ? trim($input['phone']) : null;
    $birthdate = isset($input['birthdate']) ? $input['birthdate'] : null;
    $country = isset($input['country']) ? trim($input['country']) : null;
    $city = isset($input['city']) ? trim($input['city']) : null;
    $genderId = isset($input['gender_id']) ? (int)$input['gender_id'] : null;
    $bio = isset($input['bio']) ? trim($input['bio']) : null;
    $roleId = isset($input['role_id']) ? (int)$input['role_id'] : 1; // default user
    $statusId = isset($input['status_id']) ? (int)$input['status_id'] : 1; // default active
    // Perfil por defecto (ruta relativa a la web frontend/back)
    $profilePicture = '/assets/img/default-avatar.png';

    $sqlInsert = "INSERT INTO users (first_name, last_name, username, email, password, phone, birthdate, country, city, gender_id, bio, status_id, role_id, profile_picture, created_at, updated_at)
                  VALUES (:first_name, :last_name, :username, :email, :password, :phone, :birthdate, :country, :city, :gender_id, :bio, :status_id, :role_id, :profile_picture, :created_at, :updated_at)";

    $stmt = $db->prepare($sqlInsert);
    $stmt->execute([
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':username' => $username,
        ':email' => $email,
        ':password' => $hashed,
        ':phone' => $phone,
        ':birthdate' => $birthdate,
        ':country' => $country,
        ':city' => $city,
        ':gender_id' => $genderId,
        ':bio' => $bio,
        ':status_id' => $statusId,
        ':role_id' => $roleId,
        ':profile_picture' => $profilePicture,
        ':created_at' => $now,
        ':updated_at' => $now
    ]);

    $newUserId = (int)$db->lastInsertId();

    // Insertar social si se proporciona
    if (isset($input['social']) && is_array($input['social'])) {
        $social = $input['social'];
        $sqlSocial = "INSERT INTO user_social (user_id, github_username, linkedin_username, twitter_username, discord_tag, personal_website, show_social_public)
                      VALUES (:user_id, :github, :linkedin, :twitter, :discord, :website, :show)";
        $stmtSocial = $db->prepare($sqlSocial);
        $stmtSocial->execute([
            ':user_id' => $newUserId,
            ':github' => $social['github_username'] ?? null,
            ':linkedin' => $social['linkedin_username'] ?? null,
            ':twitter' => $social['twitter_username'] ?? null,
            ':discord' => $social['discord_tag'] ?? null,
            ':website' => $social['personal_website'] ?? null,
            ':show' => isset($social['show_social_public']) ? (int)$social['show_social_public'] : 0
        ]);
    }

    // Commit
    $db->commit();

    // Obtener registro completo para respuesta
    $sqlFetch = "SELECT 
                    u.*, 
                    g.gender_name AS gender_name, 
                    r.role_name AS role_name, 
                    s.status_name AS status_name
                 FROM users u
                 LEFT JOIN genders g ON u.gender_id = g.gender_id
                 LEFT JOIN roles r ON u.role_id = r.role_id
                 LEFT JOIN status s ON u.status_id = s.status_id
                 WHERE u.user_id = :user_id LIMIT 1";
    $stmtFetch = $db->prepare($sqlFetch);
    $stmtFetch->execute([':user_id' => $newUserId]);
    $user = $stmtFetch->fetch(PDO::FETCH_ASSOC);

    // Adjuntar social data si existe
    $sqlSocialFetch = "SELECT github_username, linkedin_username, twitter_username, discord_tag, personal_website, show_social_public FROM user_social WHERE user_id = :user_id LIMIT 1";
    $stmtS = $db->prepare($sqlSocialFetch);
    $stmtS->execute([':user_id' => $newUserId]);
    $socialRow = $stmtS->fetch(PDO::FETCH_ASSOC);
    if ($socialRow) {
        $user = array_merge($user, $socialRow);
    } else {
        $user['github_username'] = null;
        $user['linkedin_username'] = null;
        $user['twitter_username'] = null;
        $user['discord_tag'] = null;
        $user['personal_website'] = null;
        $user['show_social_public'] = 0;
    }

    // Ocultar password en la respuesta
    if (isset($user['password'])) unset($user['password']);

    http_response_code(201);
    echo json_encode([
        'status' => 'success',
        'message' => 'Usuario creado exitosamente',
        'data' => $user
    ]);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code(500);
    error_log('cr_user PDOException: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error al crear usuario', 'details' => $e->getMessage()]);
} catch (Throwable $t) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code(500);
    error_log('cr_user Error: ' . $t->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}

?>