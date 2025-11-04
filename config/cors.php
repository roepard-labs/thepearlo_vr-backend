<?php
/**
 * CORS Configuration
 * Maneja los headers de CORS (Cross-Origin Resource Sharing)
 * para permitir peticiones desde diferentes orígenes
 */

// Cargar variables de entorno
require_once __DIR__ . '/env.php';

class CorsHandler
{
    /**
     * Aplica los headers CORS basados en la configuración del .env
     */
    public static function handleCors()
    {
        // Obtener orígenes permitidos desde el .env
        $allowedOrigins = EnvLoader::get('CORS_ALLOWED_ORIGINS', '*');

        // Manejar OPTIONS request (preflight)
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            self::setHeaders($allowedOrigins);
            http_response_code(200);
            exit;
        }

        // Establecer headers CORS para todas las peticiones
        self::setHeaders($allowedOrigins);
    }

    /**
     * Establece los headers CORS
     * @param string $allowedOrigins - Orígenes permitidos (* o lista separada por comas)
     */
    private static function setHeaders($allowedOrigins)
    {
        // Obtener origen de la petición (HTTP_ORIGIN o HTTP_REFERER como fallback)
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Si no hay HTTP_ORIGIN, intentar extraer del Referer
        if (empty($origin) && !empty($_SERVER['HTTP_REFERER'])) {
            $referer = $_SERVER['HTTP_REFERER'];
            if (preg_match('#^(https?://[^/]+)#', $referer, $matches)) {
                $origin = $matches[1];
            }
        }

        // Si es *, permitir cualquier origen (SIN credentials)
        if ($allowedOrigins === '*') {
            header("Access-Control-Allow-Origin: *");
        } else {
            // Validar el origen contra la lista permitida
            $originsArray = array_map('trim', explode(',', $allowedOrigins));

            if (!empty($origin) && in_array($origin, $originsArray)) {
                // Origen válido → permitir con credentials
                header("Access-Control-Allow-Origin: $origin");
                header("Access-Control-Allow-Credentials: true");
            } elseif (!empty($origin)) {
                // Origen presente pero no en la lista → permitir sin credentials
                header("Access-Control-Allow-Origin: $origin");
                header("Access-Control-Allow-Credentials: true");
            } else {
                // Sin origen → usar el primero de la lista
                $defaultOrigin = $originsArray[0] ?? 'https://website.roepard.online';
                header("Access-Control-Allow-Origin: $defaultOrigin");
                header("Access-Control-Allow-Credentials: true");
            }
        }

        // Headers permitidos
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");
        header("Access-Control-Max-Age: 86400"); // Cache preflight por 24 horas
    }
}

// Aplicar CORS automáticamente cuando se incluya este archivo
CorsHandler::handleCors();