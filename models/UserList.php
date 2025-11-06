<?php
// Requiere el conexion a la base de datos
require_once __DIR__ . '/../core/db.php';

// Clase UserList
class User
{
    private $db;

    // Crea una nueva instancia
    public function __construct()
    {
        $auth = new DBConfig();
        $this->db = $auth->getConnection();
    }

    // Obtiene todos los usuarios con género y redes sociales
    public function getAllUsers()
    {
        $stmt = $this->db->query("
            SELECT 
                u.user_id,
                u.username,
                u.email,
                u.phone,
                u.country,
                u.city,
                u.first_name,
                u.last_name,
                u.bio,
                u.gender_id,
                g.gender_name,
                u.status_id,
                s.status_name,
                r.role_id,
                r.role_name,
                u.created_at,
                u.updated_at,
                u.birthdate,
                u.profile_picture
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            JOIN status s ON u.status_id = s.status_id
            LEFT JOIN genders g ON u.gender_id = g.gender_id
            ORDER BY u.user_id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
