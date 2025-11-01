<?php
// Requiere el conexion a la base de datos
require_once __DIR__ . '/../core/db.php';

/**
 * Modelo User - Solo maneja acceso a datos
 * Siguiendo el patrón MVC estricto
 */
class User {
    private $db;

    // Crea una nueva instancia
    public function __construct() {
        $dbConfig = new DBConfig();
        $this->db = $dbConfig->getConnection();
    }

    /**
     * Busca un usuario por credenciales (email, teléfono o username)
     * Solo maneja acceso a datos - NO lógica de negocio
     */
    public function findByCredentials($input): mixed {
        try {
            if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
                $sql = "SELECT * FROM users WHERE email = :input";
            } elseif (is_numeric($input)) {
                $sql = "SELECT * FROM users WHERE phone = :input";
            } else {
                $sql = "SELECT * FROM users WHERE username = :input";
            }

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':input', $input, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al buscar usuario: " . $e->getMessage());
        }
    }

    /**
     * Actualiza el hash de contraseña
     * Solo maneja acceso a datos - NO valida ni procesa
     */
    public function updatePassword($userId, $newHash): bool {
        try {
            $sql = "UPDATE users SET password = :password, updated_at = NOW() WHERE user_id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':password', $newHash, PDO::PARAM_STR);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error al actualizar contraseña: " . $e->getMessage());
        }
    }

    /**
     * Obtiene un usuario por ID
     * Método adicional para separar responsabilidades
     */
    public function findById($userId): mixed {
        try {
            $sql = "SELECT * FROM users WHERE user_id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al buscar usuario por ID: " . $e->getMessage());
        }
    }

    /**
     * Verifica si un usuario está activo
     * Solo acceso a datos - el servicio decide qué hacer
     */
    public function isActiveUser($userId): bool {
        try {
            $sql = "SELECT status_id FROM users WHERE user_id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result && (int)$result['status_id'] === 1;
        } catch (PDOException $e) {
            throw new Exception("Error al verificar estado del usuario: " . $e->getMessage());
        }
    }
}
?>