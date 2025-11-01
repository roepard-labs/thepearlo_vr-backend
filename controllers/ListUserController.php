<?php
// Requiere el servicio
require_once __DIR__ . '/../services/UserListService.php';

// Clase controlador
class ListUserController {
    private $UserListService;

    // Crea una instancia del servicio de usuario
    public function __construct() {
        $this->UserListService = new UserListService();
    }

    // Este método maneja la petición HTTP
    public function handleRequest() {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            try {
                $users = $this->UserListService->listUsers();
                echo json_encode(['success' => true, 'data' => $users]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Error al obtener usuarios',
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        }
    }
}
?>