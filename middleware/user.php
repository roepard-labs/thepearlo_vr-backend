<?php
// Establece el encabezado de respuesta a JSON
header('Content-Type: application/json');
require_once __DIR__ . '/../core/session.php';

// Clase Auth del middleware
class Auth {
    private static $db;

    // Método para obtener la conexión a la base de datos
    private static function getDB() {
        if (!self::$db) {
            require_once __DIR__ . '/../core/db.php';
            $auth = new DBConfig();
            self::$db = $auth->getConnection();
        }
        return self::$db;
    }

    // Método para verificar si el usuario está autenticado
    public static function checkAuth() {
        ensure_session_started();
        if (!isset($_SESSION['user_id'])) {
            echo json_encode([
                'logged' => false,
                'error' => 'No autorizado'
            ]);
            exit();
        } else {
            echo json_encode([
                'logged' => true
            ]);
        }
        return $_SESSION['user_id'];
    }

    // Método para verificar autenticación sin enviar respuesta (para uso en rutas)
    public static function requireAuth() {
        ensure_session_started();
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'No autorizado - sesión requerida'
            ]);
            exit();
        }
        return $_SESSION['user_id'];
    }

    // Método para verificar si el usuario tiene el rol requerido
    public static function checkRole($required_role_id) {
        $user_id = self::requireAuth(); // Usar requireAuth en lugar de checkAuth
        
        $db = self::getDB();
        $query = "SELECT role_id FROM users WHERE user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || $user['role_id'] != $required_role_id) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'No tienes permisos para realizar esta acción'
            ]);
            exit;
        }
    }

    // Método para verificar si el usuario tiene al menos uno de los roles permitidos
    public static function checkAnyRole($allowed_roles) {
        $user_id = self::requireAuth(); // Usar requireAuth en lugar de checkAuth
        
        $db = self::getDB();
        $query = "SELECT role_id FROM users WHERE user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !in_array($user['role_id'], $allowed_roles)) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'No tienes permisos para acceder a esta función'
            ]);
            exit;
        }
    }
}
