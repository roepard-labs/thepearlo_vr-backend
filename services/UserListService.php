<?php
// Requiere el modelo
require_once __DIR__ . '/../models/UserList.php';

class UserListService {
    private $db;

    // Crea una nueva instancia
    public function __construct() {
        require_once __DIR__ . '/../core/db.php';
        $dbConfig = new DBConfig();
        $this->db = $dbConfig->getConnection();
    }

    // Obtiene todos los usuarios
    public function listUsers() {
        $stmt = $this->db->prepare("SELECT * FROM users");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
