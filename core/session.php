<?php
// Configuración centralizada de sesión
// Debe incluirse antes de cualquier session_start()

// Intentar cargar EnvLoader si existe (para usar variables ya cargadas del .env)
if (!class_exists('EnvLoader')) {
    $envPath = __DIR__ . '/../config/env.php';
    if (file_exists($envPath)) {
        require_once $envPath;
        // Cargar .env si aún no se cargó
        $dotEnv = __DIR__ . '/../../.env';
        if (file_exists($dotEnv)) {
            try { EnvLoader::load($dotEnv); } catch (Exception $e) { /* silencioso */ }
        }
    }
}

// Helper interno para obtener variables de entorno con fallback
function _env($key, $default = null) {
    if (function_exists('EnvLoader::get')) { // no callable en esta forma, se usa clase directamente
        if (class_exists('EnvLoader')) {
            return EnvLoader::get($key, $default);
        }
    }
    $val = getenv($key);
    return ($val === false || $val === null) ? $default : $val;
}

// Detección base de HTTPS
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

// Override directo (FORCE_SECURE_COOKIE) o override específico SESSION_SECURE_OVERRIDE
$forceSecure = _env('FORCE_SECURE_COOKIE');
if ($forceSecure !== null && $forceSecure !== '') {
    $isHttps = filter_var($forceSecure, FILTER_VALIDATE_BOOL);
}
$secureOverride = _env('SESSION_SECURE_OVERRIDE');
if ($secureOverride !== null && $secureOverride !== '') {
    $isHttps = filter_var($secureOverride, FILTER_VALIDATE_BOOL);
}

// Variables adicionales
$sessionName   = _env('SESSION_NAME', 'ROEPARDSESSID');
$sameSite      = ucfirst(strtolower(_env('SESSION_SAMESITE', 'Lax'))); // Normaliza
if (!in_array($sameSite, ['Lax','Strict','None'])) { $sameSite = 'Lax'; }
// Nota: SameSite=None requiere secure=true según navegadores modernos
if ($sameSite === 'None' && !$isHttps) { $sameSite = 'Lax'; }

$lifetime      = (int) (_env('SESSION_LIFETIME', 0));
$cookiePath    = _env('SESSION_COOKIE_PATH', '/');
$cookieDomain  = _env('SESSION_COOKIE_DOMAIN', '');
$httpOnlyFlag  = _env('SESSION_HTTPONLY', 'true');
$httpOnly      = filter_var($httpOnlyFlag, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
if ($httpOnly === null) { $httpOnly = true; }

if (session_status() === PHP_SESSION_NONE) {
    session_name($sessionName);
}

$cookieParams = [
    'lifetime' => $lifetime,
    'path' => $cookiePath,
    'domain' => $cookieDomain,
    'secure' => $isHttps,
    'httponly' => $httpOnly,
    'samesite' => $sameSite
];

// Aplicar configuración de cookie
if (PHP_VERSION_ID < 70300) {
    // Fallback: anexar samesite manualmente
    session_set_cookie_params(
        $cookieParams['lifetime'],
        $cookieParams['path'] . '; samesite=' . $cookieParams['samesite'],
        $cookieParams['domain'],
        $cookieParams['secure'],
        $cookieParams['httponly']
    );
} else {
    session_set_cookie_params($cookieParams);
}

// Helper para asegurar que la sesión esté iniciada
function ensure_session_started() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

// Helper para regeneración segura (evitar llamadas repetidas en la misma solicitud)
function session_regenerate_once() {
    static $regenerated = false;
    if (!$regenerated) {
        session_regenerate_id(true);
        $regenerated = true;
    }
}

?>