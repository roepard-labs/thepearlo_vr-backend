<?php
/**
 * Modelo Folder - Solo maneja acceso a datos de carpetas
 * Siguiendo el patrón MVC estricto
 * HomeLab AR - Roepard Labs
 */

require_once __DIR__ . '/../core/db.php';

class Folder
{
    private $db;

    public function __construct()
    {
        $dbConfig = new DBConfig();
        $this->db = $dbConfig->getConnection();
    }

    /**
     * Crear nueva carpeta
     */
    public function create($data): mixed
    {
        try {
            $sql = "INSERT INTO folders (
                user_id, parent_folder_id, folder_name, 
                folder_path, description, is_shared
            ) VALUES (
                :user_id, :parent_folder_id, :folder_name,
                :folder_path, :description, :is_shared
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $data['user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':parent_folder_id', $data['parent_folder_id'], PDO::PARAM_INT);
            $stmt->bindParam(':folder_name', $data['folder_name'], PDO::PARAM_STR);
            $stmt->bindParam(':folder_path', $data['folder_path'], PDO::PARAM_STR);
            $stmt->bindParam(':description', $data['description'], PDO::PARAM_STR);
            $stmt->bindParam(':is_shared', $data['is_shared'], PDO::PARAM_INT);

            $stmt->execute();
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception("Error al crear carpeta: " . $e->getMessage());
        }
    }

    /**
     * Obtener carpeta por ID
     */
    public function findById($folderId): mixed
    {
        try {
            $sql = "SELECT * FROM folders WHERE folder_id = :folder_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':folder_id', $folderId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener carpeta: " . $e->getMessage());
        }
    }

    /**
     * Listar carpetas de un usuario
     */
    public function listByUser($userId, $parentFolderId = null): mixed
    {
        try {
            if ($parentFolderId === null) {
                $sql = "SELECT * FROM folders 
                        WHERE user_id = :user_id AND parent_folder_id IS NULL
                        ORDER BY folder_name ASC";
                $stmt = $this->db->prepare($sql);
                $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            } else {
                $sql = "SELECT * FROM folders 
                        WHERE user_id = :user_id AND parent_folder_id = :parent_folder_id
                        ORDER BY folder_name ASC";
                $stmt = $this->db->prepare($sql);
                $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $stmt->bindParam(':parent_folder_id', $parentFolderId, PDO::PARAM_INT);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al listar carpetas: " . $e->getMessage());
        }
    }

    /**
     * Listar TODAS las carpetas (solo admin)
     */
    public function listAll(): mixed
    {
        try {
            $sql = "SELECT f.*, u.first_name, u.last_name, u.username 
                    FROM folders f
                    LEFT JOIN users u ON f.user_id = u.user_id
                    ORDER BY f.created_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al listar todas las carpetas: " . $e->getMessage());
        }
    }

    /**
     * Actualizar carpeta
     */
    public function update($folderId, $data): bool
    {
        try {
            $sql = "UPDATE folders SET 
                    folder_name = :folder_name,
                    description = :description,
                    is_shared = :is_shared,
                    updated_at = NOW()
                    WHERE folder_id = :folder_id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':folder_id', $folderId, PDO::PARAM_INT);
            $stmt->bindParam(':folder_name', $data['folder_name'], PDO::PARAM_STR);
            $stmt->bindParam(':description', $data['description'], PDO::PARAM_STR);
            $stmt->bindParam(':is_shared', $data['is_shared'], PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error al actualizar carpeta: " . $e->getMessage());
        }
    }

    /**
     * Eliminar carpeta (también elimina archivos asociados por CASCADE)
     */
    public function delete($folderId): bool
    {
        try {
            $sql = "DELETE FROM folders WHERE folder_id = :folder_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':folder_id', $folderId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error al eliminar carpeta: " . $e->getMessage());
        }
    }

    /**
     * Contar archivos en una carpeta
     */
    public function countFiles($folderId): int
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM files WHERE folder_id = :folder_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':folder_id', $folderId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) $result['total'];
        } catch (PDOException $e) {
            throw new Exception("Error al contar archivos: " . $e->getMessage());
        }
    }

    /**
     * Obtener ruta completa de carpeta (para breadcrumb)
     */
    public function getFullPath($folderId): array
    {
        $path = [];
        $currentId = $folderId;

        try {
            while ($currentId !== null) {
                $sql = "SELECT folder_id, folder_name, parent_folder_id 
                        FROM folders WHERE folder_id = :folder_id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindParam(':folder_id', $currentId, PDO::PARAM_INT);
                $stmt->execute();

                $folder = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$folder)
                    break;

                array_unshift($path, [
                    'folder_id' => $folder['folder_id'],
                    'folder_name' => $folder['folder_name']
                ]);

                $currentId = $folder['parent_folder_id'];
            }

            return $path;
        } catch (PDOException $e) {
            throw new Exception("Error al obtener ruta completa: " . $e->getMessage());
        }
    }
}
