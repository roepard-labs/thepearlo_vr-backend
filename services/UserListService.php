<?php
// Requiere el modelo
require_once __DIR__ . '/../models/UserList.php';

class UserListService
{
    private $db;

    // Crea una nueva instancia
    public function __construct()
    {
        require_once __DIR__ . '/../core/db.php';
        $dbConfig = new DBConfig();
        $this->db = $dbConfig->getConnection();
    }

    // Obtiene todos los usuarios con género
    public function listUsers()
    {
        $sql = "SELECT 
                    u.*,
                    g.gender_name,
                    r.role_name,
                    s.status_name
                FROM users u
                LEFT JOIN genders g ON u.gender_id = g.gender_id
                LEFT JOIN roles r ON u.role_id = r.role_id
                LEFT JOIN status s ON u.status_id = s.status_id
                ORDER BY u.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
