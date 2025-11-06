<?php
/**
 * Modelo: LegalPrivacy
 * Gestión de contenido de Política de Privacidad
 * HomeLab AR - Roepard Labs
 */

require_once __DIR__ . '/../core/db.php';

class LegalPrivacy
{
    private $db;

    public function __construct()
    {
        $dbConfig = new DBConfig();
        $this->db = $dbConfig->getConnection();
    }

    /**
     * Obtener todo el contenido de privacidad activo (para vista pública)
     */
    public function getAllActive(): array
    {
        try {
            $sql = "SELECT 
                        privacy_id,
                        section_number,
                        section_title,
                        paragraph_number,
                        paragraph_content,
                        display_order
                    FROM legal_privacy 
                    WHERE is_active = 1 
                    ORDER BY display_order ASC, section_number ASC, paragraph_number ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener privacidad: " . $e->getMessage());
        }
    }

    /**
     * Obtener todo el contenido (incluye inactivos - para admin)
     */
    public function getAll(): array
    {
        try {
            $sql = "SELECT 
                        privacy_id,
                        section_number,
                        section_title,
                        paragraph_number,
                        paragraph_content,
                        is_active,
                        display_order,
                        created_by,
                        updated_by,
                        created_at,
                        updated_at
                    FROM legal_privacy 
                    ORDER BY display_order ASC, section_number ASC, paragraph_number ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener privacidad: " . $e->getMessage());
        }
    }

    /**
     * Obtener un párrafo específico
     */
    public function getById($privacyId): mixed
    {
        try {
            $sql = "SELECT * FROM legal_privacy WHERE privacy_id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $privacyId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener párrafo: " . $e->getMessage());
        }
    }

    /**
     * Crear nuevo párrafo
     */
    public function create($data): int
    {
        try {
            $sql = "INSERT INTO legal_privacy (
                        section_number, 
                        section_title, 
                        paragraph_number, 
                        paragraph_content,
                        is_active,
                        display_order,
                        created_by
                    ) VALUES (
                        :section_number,
                        :section_title,
                        :paragraph_number,
                        :paragraph_content,
                        :is_active,
                        :display_order,
                        :created_by
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':section_number', $data['section_number'], PDO::PARAM_INT);
            $stmt->bindParam(':section_title', $data['section_title'], PDO::PARAM_STR);
            $stmt->bindParam(':paragraph_number', $data['paragraph_number'], PDO::PARAM_INT);
            $stmt->bindParam(':paragraph_content', $data['paragraph_content'], PDO::PARAM_STR);
            $stmt->bindParam(':is_active', $data['is_active'], PDO::PARAM_INT);
            $stmt->bindParam(':display_order', $data['display_order'], PDO::PARAM_INT);
            $stmt->bindParam(':created_by', $data['created_by'], PDO::PARAM_INT);
            $stmt->execute();

            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception("Error al crear párrafo: " . $e->getMessage());
        }
    }

    /**
     * Actualizar párrafo existente
     */
    public function update($privacyId, $data): bool
    {
        try {
            $sql = "UPDATE legal_privacy SET
                        section_number = :section_number,
                        section_title = :section_title,
                        paragraph_number = :paragraph_number,
                        paragraph_content = :paragraph_content,
                        is_active = :is_active,
                        display_order = :display_order,
                        updated_by = :updated_by
                    WHERE privacy_id = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':section_number', $data['section_number'], PDO::PARAM_INT);
            $stmt->bindParam(':section_title', $data['section_title'], PDO::PARAM_STR);
            $stmt->bindParam(':paragraph_number', $data['paragraph_number'], PDO::PARAM_INT);
            $stmt->bindParam(':paragraph_content', $data['paragraph_content'], PDO::PARAM_STR);
            $stmt->bindParam(':is_active', $data['is_active'], PDO::PARAM_INT);
            $stmt->bindParam(':display_order', $data['display_order'], PDO::PARAM_INT);
            $stmt->bindParam(':updated_by', $data['updated_by'], PDO::PARAM_INT);
            $stmt->bindParam(':id', $privacyId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error al actualizar párrafo: " . $e->getMessage());
        }
    }

    /**
     * Eliminar párrafo (soft delete - marcar como inactivo)
     */
    public function delete($privacyId, $updatedBy): bool
    {
        try {
            $sql = "UPDATE legal_privacy 
                    SET is_active = 0, updated_by = :updated_by 
                    WHERE privacy_id = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':updated_by', $updatedBy, PDO::PARAM_INT);
            $stmt->bindParam(':id', $privacyId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error al eliminar párrafo: " . $e->getMessage());
        }
    }

    /**
     * Obtener metadata del documento
     */
    public function getMetadata(): mixed
    {
        try {
            $sql = "SELECT * FROM legal_metadata WHERE document_type = 'privacy'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener metadata: " . $e->getMessage());
        }
    }

    /**
     * Actualizar metadata del documento
     */
    public function updateMetadata($data): bool
    {
        try {
            $sql = "UPDATE legal_metadata SET
                        version = :version,
                        effective_date = :effective_date,
                        updated_by = :updated_by,
                        change_log = :change_log
                    WHERE document_type = 'privacy'";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':version', $data['version'], PDO::PARAM_STR);
            $stmt->bindParam(':effective_date', $data['effective_date'], PDO::PARAM_STR);
            $stmt->bindParam(':updated_by', $data['updated_by'], PDO::PARAM_INT);
            $stmt->bindParam(':change_log', $data['change_log'], PDO::PARAM_STR);

            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error al actualizar metadata: " . $e->getMessage());
        }
    }
}
