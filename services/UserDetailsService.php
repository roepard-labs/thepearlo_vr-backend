<?php
// Requiere los modelos   
require_once __DIR__ . '/../models/UserUpdate.php';
require_once __DIR__ . '/../models/UserDetails.php';

// Clase UserService
class UserService {
    private $userModel;

    // Crea una nueva instancia
    public function __construct() {
        $this->userModel = new UserDetails();
    }

    // Obtiene un usuario por su ID
    public function getUserById($user_id) {
        return $this->userModel->findById($user_id);
    }
}
?>