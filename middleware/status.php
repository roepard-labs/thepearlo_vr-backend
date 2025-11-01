<?php

// Clase status del usuario
class Status
{
    private static $db;

    // Método para obtener la conexión a la base de datos
    private static function getDB()
    {
        // Iniciar sesión solo si no está iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!self::$db) {
            require_once __DIR__ . '/../core/db.php';
            $auth = new DBConfig();
            self::$db = $auth->getConnection();
        }
        return self::$db;
    }

    // Método para verificar si el usuario está autenticado
    public static function checkAuth()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
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

    // Método para verificar si el usuario tiene el estado requerido
    public static function checkStatus($required_status_id)
    {
        // Verificar autenticación si no hay usuario en sesión
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'No autorizado - sesión requerida'
            ]);
            exit();
        }
        
        $user_id = $_SESSION['user_id'];
        $db = self::getDB();
        $stmt = $db->prepare("SELECT status_id FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || $user['status_id'] != $required_status_id) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Acceso denegado - usuario deshabilitado'
            ]);
            exit();
        }
        
        return true; // Usuario tiene el estado requerido
    }

    // Método para verificar si el usuario tiene al menos uno de los estados permitidos
    public static function checkAnyStatus($allowed_statuses)
    {
        // Verificar autenticación si no hay usuario en sesión
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'No autorizado - sesión requerida'
            ]);
            exit();
        }
        
        $user_id = $_SESSION['user_id'];
        $db = self::getDB();
        $stmt = $db->prepare("SELECT status_id FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !in_array($user['status_id'], $allowed_statuses)) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Acceso denegado - estado de usuario inválido'
            ]);
            exit();
        }
        
        return true; // Usuario tiene un estado permitido
    }
}