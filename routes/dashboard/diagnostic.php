<?php
/**
 * System Diagnostic API
 * Verifica el estado de todos los componentes del sistema
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

    $diagnostics = [
        'overall_status' => 'healthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'components' => []
    ];

    // ===================================
    // 1. BASE DE DATOS
    // ===================================
    try {
        $stmt = $pdo->query('SELECT 1');
        $stmt->fetch();

        $diagnostics['components']['database'] = [
            'status' => 'healthy',
            'message' => 'Conexión a base de datos OK',
            'details' => [
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'database' => $_ENV['DB_DATABASE'] ?? 'N/A'
            ]
        ];
    } catch (PDOException $e) {
        $diagnostics['components']['database'] = [
            'status' => 'error',
            'message' => 'Error de conexión a base de datos',
            'details' => ['error' => $e->getMessage()]
        ];
        $diagnostics['overall_status'] = 'degraded';
    }

    // ===================================
    // 2. SESIONES PHP
    // ===================================
    if (session_status() === PHP_SESSION_ACTIVE) {
        $diagnostics['components']['sessions'] = [
            'status' => 'healthy',
            'message' => 'Sistema de sesiones activo',
            'details' => [
                'session_id' => session_id(),
                'save_path' => session_save_path(),
                'cookie_params' => session_get_cookie_params()
            ]
        ];
    } else {
        $diagnostics['components']['sessions'] = [
            'status' => 'warning',
            'message' => 'Sesiones no activas'
        ];
        $diagnostics['overall_status'] = 'degraded';
    }

    // ===================================
    // 3. TABLAS REQUERIDAS
    // ===================================
    $required_tables = ['users', 'roles', 'status', 'user_sessions'];
    $missing_tables = [];

    foreach ($required_tables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() === 0) {
                $missing_tables[] = $table;
            }
        } catch (PDOException $e) {
            $missing_tables[] = $table;
        }
    }

    if (empty($missing_tables)) {
        $diagnostics['components']['tables'] = [
            'status' => 'healthy',
            'message' => 'Todas las tablas requeridas existen',
            'details' => ['tables' => $required_tables]
        ];
    } else {
        $diagnostics['components']['tables'] = [
            'status' => 'error',
            'message' => 'Faltan tablas requeridas',
            'details' => ['missing' => $missing_tables]
        ];
        $diagnostics['overall_status'] = 'critical';
    }

    // ===================================
    // 4. PERMISOS DE ARCHIVOS
    // ===================================
    $upload_dir = __DIR__ . '/../../uploads';
    $logs_dir = __DIR__ . '/../../logs';

    $dirs_to_check = [
        'uploads' => $upload_dir,
        'logs' => $logs_dir
    ];

    $permissions_ok = true;
    $permissions_details = [];

    foreach ($dirs_to_check as $name => $path) {
        if (file_exists($path)) {
            $writable = is_writable($path);
            $permissions_details[$name] = [
                'exists' => true,
                'writable' => $writable,
                'path' => $path
            ];
            if (!$writable) {
                $permissions_ok = false;
            }
        } else {
            $permissions_details[$name] = [
                'exists' => false,
                'writable' => false,
                'path' => $path
            ];
            $permissions_ok = false;
        }
    }

    $diagnostics['components']['filesystem'] = [
        'status' => $permissions_ok ? 'healthy' : 'warning',
        'message' => $permissions_ok ? 'Permisos de archivos OK' : 'Problemas con permisos',
        'details' => $permissions_details
    ];

    if (!$permissions_ok) {
        $diagnostics['overall_status'] = 'degraded';
    }

    // ===================================
    // 5. EXTENSIONES PHP REQUERIDAS
    // ===================================
    $required_extensions = ['pdo', 'pdo_mysql', 'mbstring', 'curl', 'json'];
    $missing_extensions = [];

    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $missing_extensions[] = $ext;
        }
    }

    if (empty($missing_extensions)) {
        $diagnostics['components']['php_extensions'] = [
            'status' => 'healthy',
            'message' => 'Todas las extensiones PHP están cargadas',
            'details' => ['extensions' => $required_extensions]
        ];
    } else {
        $diagnostics['components']['php_extensions'] = [
            'status' => 'error',
            'message' => 'Faltan extensiones PHP requeridas',
            'details' => ['missing' => $missing_extensions]
        ];
        $diagnostics['overall_status'] = 'critical';
    }

    // ===================================
    // 6. CONFIGURACIÓN DE ENTORNO
    // ===================================
    $env_vars = [
        'DB_HOST',
        'DB_DATABASE',
        'DB_USERNAME',
        'CORS_ALLOWED_ORIGINS'
    ];

    $missing_env = [];
    foreach ($env_vars as $var) {
        if (!isset($_ENV[$var]) || empty($_ENV[$var])) {
            $missing_env[] = $var;
        }
    }

    if (empty($missing_env)) {
        $diagnostics['components']['environment'] = [
            'status' => 'healthy',
            'message' => 'Variables de entorno configuradas',
            'details' => ['required_vars' => $env_vars]
        ];
    } else {
        $diagnostics['components']['environment'] = [
            'status' => 'warning',
            'message' => 'Algunas variables de entorno faltan',
            'details' => ['missing' => $missing_env]
        ];
        $diagnostics['overall_status'] = 'degraded';
    }

    // ===================================
    // 7. INFORMACIÓN DEL SISTEMA
    // ===================================
    $diagnostics['components']['system'] = [
        'status' => 'healthy',
        'message' => 'Información del sistema',
        'details' => [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        ]
    ];

    // Respuesta
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => $diagnostics
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al ejecutar diagnóstico: ' . $e->getMessage()
    ]);
}
