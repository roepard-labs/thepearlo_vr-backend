<?php
// Requiere el servicio y el modelo
require_once __DIR__ . '/../services/UserDetailsService.php';
require_once __DIR__ . '/../models/UserDetails.php';

// Clase controlador
class DetUserController {
    private $userService;

    // Crea una instancia del servicio de usuario
    public function __construct() {
        $this->userService = new UserService();
    }

    // Este método maneja la petición HTTP
    public function getUserDetails() {
        try {
            if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
                throw new Exception("ID de usuario no proporcionado");
            }
            
            $user = $this->userService->getUserById($_POST['user_id']);
            if (!$user) {
                throw new Exception("Usuario no encontrado");
            }

            echo json_encode([
                'status' => 'success',
                'data' => $user
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
}
?>