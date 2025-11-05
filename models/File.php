<?php
/**
 * Modelo File - Solo maneja acceso a datos
 * Siguiendo el patrón MVC estricto
 * HomeLab AR - Roepard Labs
 */

require_once __DIR__ . '/../core/db.php';

class File
{
    private $db;

    public function __construct()
    {
        $dbConfig = new DBConfig();
        $this->db = $dbConfig->getConnection();
    }

    /**
     * Crear nuevo archivo en BD
     * Solo acceso a datos - NO lógica de negocio
     */
    public function create($data): mixed
    {
        try {
            $sql = "INSERT INTO files (
                user_id, folder_id, file_name, original_name, 
                file_path, file_size, file_type, file_extension, 
                description, is_shared
            ) VALUES (
                :user_id, :folder_id, :file_name, :original_name,
                :file_path, :file_size, :file_type, :file_extension,
                :description, :is_shared
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $data['user_id'], PDO::PARAM_INT);

            // CRÍTICO: Permitir NULL para folder_id (archivos en raíz)
            // Si folder_id es NULL, usar PDO::PARAM_NULL, sino PDO::PARAM_INT
            if ($data['folder_id'] === null) {
                $stmt->bindValue(':folder_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindParam(':folder_id', $data['folder_id'], PDO::PARAM_INT);
            }

            $stmt->bindParam(':file_name', $data['file_name'], PDO::PARAM_STR);
            $stmt->bindParam(':original_name', $data['original_name'], PDO::PARAM_STR);
            $stmt->bindParam(':file_path', $data['file_path'], PDO::PARAM_STR);
            $stmt->bindParam(':file_size', $data['file_size'], PDO::PARAM_INT);
            $stmt->bindParam(':file_type', $data['file_type'], PDO::PARAM_STR);
            $stmt->bindParam(':file_extension', $data['file_extension'], PDO::PARAM_STR);
            $stmt->bindParam(':description', $data['description'], PDO::PARAM_STR);
            $stmt->bindParam(':is_shared', $data['is_shared'], PDO::PARAM_INT);

            $stmt->execute();
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception("Error al crear archivo: " . $e->getMessage());
        }
    }

    /**
     * Obtener archivo por ID
     */
    public function findById($fileId): mixed
    {
        try {
            $sql = "SELECT f.*, u.first_name, u.last_name, u.username 
                    FROM files f
                    LEFT JOIN users u ON f.user_id = u.user_id
                    WHERE f.file_id = :file_id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':file_id', $fileId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener archivo: " . $e->getMessage());
        }
    }

    /**
     * Listar archivos de un usuario en una carpeta específica
     */
    public function listByUserAndFolder($userId, $folderId = null): mixed
    {
        try {
            if ($folderId === null) {
                // CRÍTICO: Incluir información del usuario con JOIN
                $sql = "SELECT f.*, u.first_name, u.last_name, u.username 
                        FROM files f
                        LEFT JOIN users u ON f.user_id = u.user_id
                        WHERE f.user_id = :user_id AND f.folder_id IS NULL
                        ORDER BY f.created_at DESC";
                $stmt = $this->db->prepare($sql);
                $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            } else {
                // CRÍTICO: Incluir información del usuario con JOIN
                $sql = "SELECT f.*, u.first_name, u.last_name, u.username 
                        FROM files f
                        LEFT JOIN users u ON f.user_id = u.user_id
                        WHERE f.user_id = :user_id AND f.folder_id = :folder_id
                        ORDER BY f.created_at DESC";
                $stmt = $this->db->prepare($sql);
                $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $stmt->bindParam(':folder_id', $folderId, PDO::PARAM_INT);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al listar archivos: " . $e->getMessage());
        }
    }

    /**
     * Listar TODOS los archivos (solo para admin)
     */
    public function listAll(): mixed
    {
        try {
            $sql = "SELECT f.*, u.first_name, u.last_name, u.username 
                    FROM files f
                    LEFT JOIN users u ON f.user_id = u.user_id
                    ORDER BY f.created_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al listar todos los archivos: " . $e->getMessage());
        }
    }

    /**
     * Listar archivos de una carpeta específica (todos los usuarios, para admin)
     */
    public function listByFolder($folderId): mixed
    {
        error_log("🗂️ File::listByFolder() llamado con folder_id: " . $folderId);
        try {
            $sql = "SELECT f.*, u.first_name, u.last_name, u.username 
                    FROM files f
                    LEFT JOIN users u ON f.user_id = u.user_id
                    WHERE f.folder_id = :folder_id
                    ORDER BY f.created_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':folder_id', $folderId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("📁 File::listByFolder() retornó " . count($result) . " archivos");
            error_log("📋 Archivos: " . print_r(array_column($result, 'original_name'), true));

            return $result;
        } catch (PDOException $e) {
            error_log("❌ Error en File::listByFolder(): " . $e->getMessage());
            throw new Exception("Error al listar archivos de carpeta: " . $e->getMessage());
        }
    }

    /**
     * Actualizar metadata de archivo
     */
    public function update($fileId, $data): bool
    {
        try {
            // CRÍTICO: Actualizar original_name (nombre visible) no file_name (nombre físico)
            $sql = "UPDATE files SET 
                    original_name = :original_name,
                    description = :description,
                    is_shared = :is_shared,
                    updated_at = NOW()
                    WHERE file_id = :file_id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':file_id', $fileId, PDO::PARAM_INT);
            $stmt->bindParam(':original_name', $data['original_name'], PDO::PARAM_STR);
            $stmt->bindParam(':description', $data['description'], PDO::PARAM_STR);
            $stmt->bindParam(':is_shared', $data['is_shared'], PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error al actualizar archivo: " . $e->getMessage());
        }
    }

    /**
     * Eliminar archivo de BD
     */
    public function delete($fileId): bool
    {
        try {
            $sql = "DELETE FROM files WHERE file_id = :file_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':file_id', $fileId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error al eliminar archivo: " . $e->getMessage());
        }
    }

    /**
     * Incrementar contador de descargas
     */
    public function incrementDownloads($fileId): bool
    {
        try {
            $sql = "UPDATE files SET downloads = downloads + 1 WHERE file_id = :file_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':file_id', $fileId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error al incrementar descargas: " . $e->getMessage());
        }
    }

    /**
     * Obtener estadísticas de almacenamiento de un usuario
     */
    public function getUserStats($userId): mixed
    {
        try {
            // Estadísticas de archivos
            $sqlFiles = "SELECT 
                    COUNT(*) as total_files,
                    COALESCE(SUM(file_size), 0) as total_size,
                    COUNT(CASE WHEN is_shared = 1 THEN 1 END) as shared_files
                    FROM files 
                    WHERE user_id = :user_id";

            $stmtFiles = $this->db->prepare($sqlFiles);
            $stmtFiles->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmtFiles->execute();
            $filesStats = $stmtFiles->fetch(PDO::FETCH_ASSOC);

            // Estadísticas de carpetas
            $sqlFolders = "SELECT COUNT(*) as total_folders
                           FROM folders 
                           WHERE user_id = :user_id";

            $stmtFolders = $this->db->prepare($sqlFolders);
            $stmtFolders->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmtFolders->execute();
            $foldersStats = $stmtFolders->fetch(PDO::FETCH_ASSOC);

            // Combinar estadísticas
            return [
                'total_files' => $filesStats['total_files'],
                'total_size' => $filesStats['total_size'],
                'shared_files' => $filesStats['shared_files'],
                'total_folders' => $foldersStats['total_folders']
            ];

        } catch (PDOException $e) {
            throw new Exception("Error al obtener estadísticas: " . $e->getMessage());
        }
    }

    /**
     * Buscar archivos por nombre (para admin o usuario)
     */
    public function search($userId, $searchTerm, $isAdmin = false): mixed
    {
        try {
            if ($isAdmin) {
                $sql = "SELECT f.*, u.first_name, u.last_name 
                        FROM files f
                        LEFT JOIN users u ON f.user_id = u.user_id
                        WHERE f.file_name LIKE :search OR f.original_name LIKE :search
                        ORDER BY f.created_at DESC";
                $stmt = $this->db->prepare($sql);
            } else {
                $sql = "SELECT * FROM files 
                        WHERE user_id = :user_id 
                        AND (file_name LIKE :search OR original_name LIKE :search)
                        ORDER BY created_at DESC";
                $stmt = $this->db->prepare($sql);
                $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            }

            $searchParam = "%{$searchTerm}%";
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al buscar archivos: " . $e->getMessage());
        }
    }
}
