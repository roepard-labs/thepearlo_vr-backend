<?php
/**
 * CORS Configuration
 * Maneja los headers de CORS (Cross-Origin Resource Sharing)
 * para permitir peticiones desde diferentes orígenes
 */

// Cargar variables de entorno
require_once __DIR__ . '/env.php';

class CorsHandler {
    /**
     * Aplica los headers CORS basados en la configuración del .env
     */
    public static function handleCors() {
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
    private static function setHeaders($allowedOrigins) {
        // Si es *, permitir cualquier origen
        if ($allowedOrigins === '*') {
            header("Access-Control-Allow-Origin: *");
        } else {
            // Validar el origen de la petición contra la lista permitida
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            $originsArray = array_map('trim', explode(',', $allowedOrigins));
            
            if (in_array($origin, $originsArray)) {
                header("Access-Control-Allow-Origin: $origin");
                // Permitir credenciales cuando no es *
                header("Access-Control-Allow-Credentials: true");
            } else {
                // Si el origen no está en la lista, pero allowedOrigins no es vacío
                // usar el primero de la lista O permitir el origen de todas formas
                if (!empty($origin)) {
                    // En desarrollo, permitir el origen aunque no esté en la lista
                    header("Access-Control-Allow-Origin: $origin");
                    header("Access-Control-Allow-Credentials: true");
                } else {
                    // Sin origen en la petición, usar el primero de la lista
                    header("Access-Control-Allow-Origin: " . ($originsArray[0] ?? '*'));
                }
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