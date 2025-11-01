<?php
// Requiere el conexion a la base de datos
require_once __DIR__ . '/../core/db.php';

// Clase UserDetails
class UserDetails {
    private $db;

    // Crea una nueva instancia
    public function __construct() {
        $dbConfig = new DBConfig();
        $this->db = $dbConfig->getConnection();
    }

    // Busca un usuario por su ID
    public function findById($user_id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>