<?php
/**
 * UserSession Model
 * Gestión de sesiones de usuario en base de datos
 * HomeLab AR - Roepard Labs
 */

require_once __DIR__ . '/../core/db.php';

class UserSession
{
    private $conn;
    private $table = 'user_sessions';

    public function __construct()
    {
        $dbConfig = new DBConfig();
        $this->conn = $dbConfig->getConnection();
    }

    /**
     * Crear nueva sesión en la base de datos
     */
    public function createSession(array $data): bool
    {
        try {
            // Verificar si ya existe una sesión con este session_id
            $checkQuery = "SELECT session_id FROM {$this->table} WHERE session_id = :session_id";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(':session_id', $data['session_id']);
            $checkStmt->execute();

            if ($checkStmt->rowCount() > 0) {
                // Ya existe, actualizar
                return $this->updateExistingSession($data);
            }

            // Insertar nueva sesión
            $query = "INSERT INTO {$this->table} 
                      (session_id, user_id, ip_address, user_agent, browser, os, device_type, expires_at) 
                      VALUES 
                      (:session_id, :user_id, :ip_address, :user_agent, :browser, :os, :device_type, :expires_at)";

            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(':session_id', $data['session_id']);
            $stmt->bindParam(':user_id', $data['user_id']);
            $stmt->bindParam(':ip_address', $data['ip_address']);
            $stmt->bindParam(':user_agent', $data['user_agent']);
            $stmt->bindParam(':browser', $data['browser']);
            $stmt->bindParam(':os', $data['os']);
            $stmt->bindParam(':device_type', $data['device_type']);
            $stmt->bindParam(':expires_at', $data['expires_at']);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Error creating session: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar sesión existente (renovar expiración)
     */
    private function updateExistingSession(array $data): bool
    {
        try {
            $query = "UPDATE {$this->table} 
                      SET is_active = 1,
                          expires_at = :expires_at,
                          last_activity = CURRENT_TIMESTAMP,
                          closed_at = NULL,
                          closed_by = NULL,
                          close_reason = NULL
                      WHERE session_id = :session_id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':session_id', $data['session_id']);
            $stmt->bindParam(':expires_at', $data['expires_at']);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Error updating existing session: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener sesión por ID
     */
    public function getSessionById(string $sessionId): mixed
    {
        try {
            $query = "SELECT * FROM {$this->table} WHERE session_id = :session_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':session_id', $sessionId);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error getting session by ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener todas las sesiones activas de un usuario
     */
    public function getActiveSessions(int $userId): array
    {
        try {
            $query = "SELECT 
                        session_id,
                        ip_address,
                        browser,
                        os,
                        device_type,
                        created_at,
                        last_activity,
                        expires_at,
                        TIMESTAMPDIFF(MINUTE, NOW(), expires_at) AS minutes_remaining
                      FROM {$this->table}
                      WHERE user_id = :user_id 
                        AND is_active = 1
                        AND expires_at > NOW()
                      ORDER BY last_activity DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error getting active sessions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener historial de sesiones de un usuario (últimas 30 días)
     */
    public function getSessionHistory(int $userId, int $limit = 20): array
    {
        try {
            $query = "SELECT 
                        session_id,
                        ip_address,
                        browser,
                        os,
                        device_type,
                        created_at,
                        closed_at,
                        close_reason,
                        TIMESTAMPDIFF(MINUTE, created_at, COALESCE(closed_at, expires_at)) AS session_duration_minutes
                      FROM {$this->table}
                      WHERE user_id = :user_id 
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                      ORDER BY created_at DESC
                      LIMIT :limit";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error getting session history: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Actualizar última actividad de la sesión
     */
    public function updateLastActivity(string $sessionId): bool
    {
        try {
            $query = "UPDATE {$this->table} 
                      SET last_activity = CURRENT_TIMESTAMP 
                      WHERE session_id = :session_id 
                        AND is_active = 1";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':session_id', $sessionId);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Error updating last activity: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cerrar sesión
     */
    public function closeSession(string $sessionId, ?int $closedByUserId = null, string $reason = 'logout'): bool
    {
        try {
            $query = "UPDATE {$this->table} 
                      SET is_active = 0,
                          closed_at = NOW(),
                          closed_by = :closed_by,
                          close_reason = :reason
                      WHERE session_id = :session_id 
                        AND is_active = 1";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':session_id', $sessionId);
            $stmt->bindParam(':closed_by', $closedByUserId, PDO::PARAM_INT);
            $stmt->bindParam(':reason', $reason);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Error closing session: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cerrar todas las sesiones de un usuario (excepto la actual)
     */
    public function closeAllUserSessions(int $userId, ?string $exceptSessionId = null): int
    {
        try {
            if ($exceptSessionId) {
                $query = "UPDATE {$this->table} 
                          SET is_active = 0,
                              closed_at = NOW(),
                              closed_by = :user_id,
                              close_reason = 'remote'
                          WHERE user_id = :user_id 
                            AND session_id != :except_session_id
                            AND is_active = 1";

                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $stmt->bindParam(':except_session_id', $exceptSessionId);
            } else {
                $query = "UPDATE {$this->table} 
                          SET is_active = 0,
                              closed_at = NOW(),
                              closed_by = :user_id,
                              close_reason = 'remote'
                          WHERE user_id = :user_id 
                            AND is_active = 1";

                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            }

            $stmt->execute();
            return $stmt->rowCount();

        } catch (PDOException $e) {
            error_log("Error closing all user sessions: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Limpiar sesiones expiradas
     */
    public function cleanupExpiredSessions(): int
    {
        try {
            $query = "UPDATE {$this->table} 
                      SET is_active = 0,
                          closed_at = expires_at,
                          close_reason = 'expired'
                      WHERE is_active = 1 
                        AND expires_at < NOW()
                        AND closed_at IS NULL";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            return $stmt->rowCount();

        } catch (PDOException $e) {
            error_log("Error cleaning up expired sessions: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Contar sesiones activas por usuario
     */
    public function countActiveSessions(int $userId): int
    {
        try {
            $query = "SELECT COUNT(*) as total 
                      FROM {$this->table} 
                      WHERE user_id = :user_id 
                        AND is_active = 1
                        AND expires_at > NOW()";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) $result['total'];

        } catch (PDOException $e) {
            error_log("Error counting active sessions: " . $e->getMessage());
            return 0;
        }
    }
}
