<?php
/**
 * Session Tracker Middleware
 * Registra y rastrea sesiones de usuario en la base de datos
 * HomeLab AR - Roepard Labs
 */

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../models/UserSession.php';

class SessionTracker
{
    /**
     * Registrar nueva sesión en la base de datos
     */
    public static function trackSession($userId): void
    {
        try {
            $sessionId = session_id();
            $ipAddress = self::getRealIpAddress();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

            // Parsear información del navegador
            $browserInfo = self::parseUserAgent($userAgent);

            // Calcular fecha de expiración (1 hora desde ahora)
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);

            $userSession = new UserSession();
            $userSession->createSession([
                'session_id' => $sessionId,
                'user_id' => $userId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'browser' => $browserInfo['browser'],
                'os' => $browserInfo['os'],
                'device_type' => $browserInfo['device_type'],
                'expires_at' => $expiresAt
            ]);

        } catch (Exception $e) {
            error_log("Error tracking session: " . $e->getMessage());
            // No lanzar excepción para no interrumpir el login
        }
    }

    /**
     * Actualizar última actividad de la sesión
     */
    public static function updateActivity(): void
    {
        try {
            $sessionId = session_id();
            if (!empty($sessionId)) {
                $userSession = new UserSession();
                $userSession->updateLastActivity($sessionId);
            }
        } catch (Exception $e) {
            error_log("Error updating session activity: " . $e->getMessage());
        }
    }

    /**
     * Cerrar sesión en la base de datos
     */
    public static function closeSession(string $reason = 'logout'): void
    {
        try {
            $sessionId = session_id();
            if (!empty($sessionId)) {
                $userSession = new UserSession();
                $userSession->closeSession($sessionId, null, $reason);
            }
        } catch (Exception $e) {
            error_log("Error closing session: " . $e->getMessage());
        }
    }

    /**
     * Verificar si la sesión sigue activa en la base de datos
     */
    public static function isSessionValid(): bool
    {
        try {
            $sessionId = session_id();
            if (empty($sessionId)) {
                return false;
            }

            $userSession = new UserSession();
            $session = $userSession->getSessionById($sessionId);

            if (!$session) {
                return false;
            }

            // Verificar si la sesión está activa
            if ($session['is_active'] != 1) {
                return false;
            }

            // Verificar si la sesión expiró
            $expiresAt = strtotime($session['expires_at']);
            if ($expiresAt < time()) {
                // Marcar como expirada
                $userSession->closeSession($sessionId, null, 'expired');
                return false;
            }

            return true;

        } catch (Exception $e) {
            error_log("Error validating session: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener IP real del usuario (considerando proxies)
     */
    private static function getRealIpAddress(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',  // Cloudflare
            'HTTP_X_REAL_IP',          // Nginx proxy
            'HTTP_X_FORWARDED_FOR',    // Standard forwarded
            'REMOTE_ADDR'              // Directo
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];

                // Si es una lista separada por comas, tomar la primera IP
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }

                // Validar que sea una IP válida
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return 'unknown';
    }

    /**
     * Parsear User Agent para obtener navegador, OS y tipo de dispositivo
     */
    private static function parseUserAgent(string $userAgent): array
    {
        $result = [
            'browser' => 'Unknown',
            'os' => 'Unknown',
            'device_type' => 'unknown'
        ];

        // Detectar navegador
        if (preg_match('/MSIE|Trident/i', $userAgent)) {
            $result['browser'] = 'Internet Explorer';
        } elseif (preg_match('/Edge/i', $userAgent)) {
            $result['browser'] = 'Microsoft Edge';
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $result['browser'] = 'Mozilla Firefox';
        } elseif (preg_match('/Chrome/i', $userAgent) && !preg_match('/Edg/i', $userAgent)) {
            $result['browser'] = 'Google Chrome';
        } elseif (preg_match('/Safari/i', $userAgent) && !preg_match('/Chrome/i', $userAgent)) {
            $result['browser'] = 'Apple Safari';
        } elseif (preg_match('/Opera|OPR/i', $userAgent)) {
            $result['browser'] = 'Opera';
        }

        // Detectar sistema operativo
        if (preg_match('/Windows NT 10/i', $userAgent)) {
            $result['os'] = 'Windows 10/11';
        } elseif (preg_match('/Windows NT 6.3/i', $userAgent)) {
            $result['os'] = 'Windows 8.1';
        } elseif (preg_match('/Windows NT 6.2/i', $userAgent)) {
            $result['os'] = 'Windows 8';
        } elseif (preg_match('/Windows NT 6.1/i', $userAgent)) {
            $result['os'] = 'Windows 7';
        } elseif (preg_match('/Windows/i', $userAgent)) {
            $result['os'] = 'Windows';
        } elseif (preg_match('/Mac OS X 10[._](\d+)/i', $userAgent, $matches)) {
            $result['os'] = 'macOS ' . $matches[1];
        } elseif (preg_match('/Mac OS X/i', $userAgent)) {
            $result['os'] = 'macOS';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $result['os'] = 'Linux';
        } elseif (preg_match('/Android (\d+)/i', $userAgent, $matches)) {
            $result['os'] = 'Android ' . $matches[1];
        } elseif (preg_match('/Android/i', $userAgent)) {
            $result['os'] = 'Android';
        } elseif (preg_match('/iPhone OS (\d+)/i', $userAgent, $matches)) {
            $result['os'] = 'iOS ' . $matches[1];
        } elseif (preg_match('/iPad/i', $userAgent)) {
            $result['os'] = 'iPadOS';
        }

        // Detectar tipo de dispositivo
        if (preg_match('/Mobile|Android|iPhone/i', $userAgent)) {
            $result['device_type'] = 'mobile';
        } elseif (preg_match('/Tablet|iPad/i', $userAgent)) {
            $result['device_type'] = 'tablet';
        } elseif (preg_match('/Windows|Macintosh|Linux/i', $userAgent)) {
            $result['device_type'] = 'desktop';
        }

        return $result;
    }
}
