<?php
require_once __DIR__ . '/../core/db.php';

/**
 * Modelo para la tabla user_homelab_config
 * Solo acceso a datos (CRUD mínimo)
 */
class UserHomelabConfig
{
    private $db;

    public function __construct()
    {
        $dbConfig = new DBConfig();
        $this->db = $dbConfig->getConnection();
    }

    /**
     * Obtener configuración por user_id
     * @param int $userId
     * @return array|null
     */
    public function getByUserId(int $userId): ?array
    {
        try {
            $sql = "SELECT * FROM user_homelab_config WHERE user_id = :user_id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            throw new Exception('Error al obtener configuración: ' . $e->getMessage());
        }
    }

    /**
     * Insertar o actualizar configuración por user_id
     * Usa INSERT ... ON DUPLICATE KEY UPDATE (MySQL/MariaDB)
     * @param int $userId
     * @param array $data
     * @return bool
     */
    public function upsertByUserId(int $userId, array $data): bool
    {
        try {
            $sql = "INSERT INTO user_homelab_config
                (user_id, theme, clock_format, color_accessibility, consent_privacy, seen_homelab_modal, preferences, created_at, updated_at)
                VALUES
                (:user_id, :theme, :clock_format, :color_accessibility, :consent_privacy, :seen_homelab_modal, :preferences, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                theme = VALUES(theme),
                clock_format = VALUES(clock_format),
                color_accessibility = VALUES(color_accessibility),
                consent_privacy = VALUES(consent_privacy),
                seen_homelab_modal = VALUES(seen_homelab_modal),
                preferences = VALUES(preferences),
                updated_at = NOW()
            ";

            $stmt = $this->db->prepare($sql);

            // Bind seguros con fallback
            $preferences = isset($data['preferences']) ? json_encode($data['preferences']) : null;
            $theme = $data['theme'] ?? 'dark';
            $clock_format = (int) ($data['clock_format'] ?? 24);
            $color_accessibility = $data['color_accessibility'] ?? 'default';
            $consent_privacy = isset($data['consent_privacy']) ? (int) $data['consent_privacy'] : 1;
            $seen = isset($data['seen_homelab_modal']) ? (int) $data['seen_homelab_modal'] : 0;

            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':theme', $theme, PDO::PARAM_STR);
            $stmt->bindParam(':clock_format', $clock_format, PDO::PARAM_INT);
            $stmt->bindParam(':color_accessibility', $color_accessibility, PDO::PARAM_STR);
            $stmt->bindParam(':consent_privacy', $consent_privacy, PDO::PARAM_INT);
            $stmt->bindParam(':seen_homelab_modal', $seen, PDO::PARAM_INT);
            $stmt->bindParam(':preferences', $preferences, PDO::PARAM_STR);

            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception('Error al insertar/actualizar configuración: ' . $e->getMessage());
        }
    }
}

?>
