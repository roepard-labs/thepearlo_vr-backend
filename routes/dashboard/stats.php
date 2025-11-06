<?php
/**
 * Dashboard Stats API
 * Retorna estadísticas del sistema para el dashboard
 * HomeLab AR - Roepard Labs
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../middleware/user.php';
require_once __DIR__ . '/../../middleware/status.php';
require_once __DIR__ . '/../../core/db.php';

// Asegurar que siempre retornamos JSON
header('Content-Type: application/json');

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Método no permitido. Use GET.'
    ]);
    exit;
}

// Aplicar middleware de seguridad
$user_id = Auth::checkAuth();
Status::checkStatus(1);

try {
    // Obtener conexión PDO
    $dbConfig = new DBConfig();
    $pdo = $dbConfig->getConnection();

    $role_id = $_SESSION['role_id'] ?? 1;

    // ===================================
    // ESTADÍSTICAS GENERALES
    // ===================================

    $stats = [
        'users' => [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'admins' => 0
        ],
        'sessions' => [
            'total' => 0,
            'active' => 0,
            'user_sessions' => 0
        ],
        'storage' => [
            'total_files' => 0,
            'total_size' => 0,
            'user_files' => 0,
            'user_size' => 0
        ],
        'activity' => [
            'logins_today' => 0,
            'logins_week' => 0,
            'logins_month' => 0
        ]
    ];

    // Estadísticas de usuarios (solo para admin)
    if ($role_id == 2) {
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status_id = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status_id = 0 THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN role_id = 2 THEN 1 ELSE 0 END) as admins
            FROM users
        ");
        $userStats = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['users'] = [
            'total' => (int) $userStats['total'],
            'active' => (int) $userStats['active'],
            'inactive' => (int) $userStats['inactive'],
            'admins' => (int) $userStats['admins']
        ];
    }

    // Estadísticas de sesiones
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
        FROM user_sessions
    ");
    $stmt->execute();
    $sessionStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Sesiones del usuario actual
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as user_sessions
        FROM user_sessions
        WHERE user_id = :user_id AND is_active = 1
    ");
    $stmt->execute(['user_id' => $user_id]);
    $userSessionCount = $stmt->fetch(PDO::FETCH_ASSOC);

    $stats['sessions'] = [
        'total' => (int) $sessionStats['total'],
        'active' => (int) $sessionStats['active'],
        'user_sessions' => (int) $userSessionCount['user_sessions']
    ];

    // Estadísticas de archivos (si existe la tabla)
    try {
        // Total de archivos en el sistema
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_files,
                COALESCE(SUM(file_size), 0) as total_size
            FROM user_files
        ");
        $fileStats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Archivos del usuario
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as user_files,
                COALESCE(SUM(file_size), 0) as user_size
            FROM user_files
            WHERE user_id = :user_id
        ");
        $stmt->execute(['user_id' => $user_id]);
        $userFileStats = $stmt->fetch(PDO::FETCH_ASSOC);

        $stats['storage'] = [
            'total_files' => (int) $fileStats['total_files'],
            'total_size' => (int) $fileStats['total_size'],
            'user_files' => (int) $userFileStats['user_files'],
            'user_size' => (int) $userFileStats['user_size']
        ];
    } catch (PDOException $e) {
        // Tabla no existe, mantener valores en 0
    }

    // Estadísticas de actividad (logins recientes)
    if ($role_id == 2) {
        // Logins hoy
        $stmt = $pdo->query("
            SELECT COUNT(*) as count
            FROM user_sessions
            WHERE DATE(created_at) = CURDATE()
        ");
        $stats['activity']['logins_today'] = (int) $stmt->fetchColumn();

        // Logins esta semana
        $stmt = $pdo->query("
            SELECT COUNT(*) as count
            FROM user_sessions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stats['activity']['logins_week'] = (int) $stmt->fetchColumn();

        // Logins este mes
        $stmt = $pdo->query("
            SELECT COUNT(*) as count
            FROM user_sessions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stats['activity']['logins_month'] = (int) $stmt->fetchColumn();
    }

    // ===================================
    // DATOS PARA GRÁFICAS
    // ===================================

    $charts = [
        'users_by_role' => [],
        'sessions_by_day' => [],
        'storage_by_user' => []
    ];

    // Gráfica: Usuarios por rol (solo admin)
    if ($role_id == 2) {
        $stmt = $pdo->query("
            SELECT r.role_name, COUNT(u.user_id) as count
            FROM roles r
            LEFT JOIN users u ON r.role_id = u.role_id
            GROUP BY r.role_id, r.role_name
            ORDER BY count DESC
        ");
        $charts['users_by_role'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Gráfica: Sesiones por día (últimos 7 días)
        $stmt = $pdo->query("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as count
            FROM user_sessions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $charts['sessions_by_day'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Gráfica: Top 5 usuarios por almacenamiento
        try {
            $stmt = $pdo->query("
                SELECT 
                    u.first_name,
                    u.last_name,
                    COALESCE(SUM(f.file_size), 0) as total_size
                FROM users u
                LEFT JOIN user_files f ON u.user_id = f.user_id
                GROUP BY u.user_id, u.first_name, u.last_name
                ORDER BY total_size DESC
                LIMIT 5
            ");
            $charts['storage_by_user'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $charts['storage_by_user'] = [];
        }
    }

    // Respuesta exitosa
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => [
            'stats' => $stats,
            'charts' => $charts,
            'role_id' => $role_id
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
    ]);
}
