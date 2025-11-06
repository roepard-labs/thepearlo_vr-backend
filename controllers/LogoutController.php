<?php
header('Content-Type: application/json'); // Establece el tipo de contenido a JSON
// Requiere el servicio
require_once __DIR__ . '/../services/LogoutService.php';
require_once __DIR__ . '/../middleware/session_tracker.php';

// Clase controlador
class LogoutController {
    private $logoutService;

    // Crea una instancia del servicio de logout
    public function __construct() {
        $this->logoutService = new LogoutService();
    }

    // Este método maneja la petición HTTP
    public function handleRequest(): void {
        // Verifica que la petición sea POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // ✅ NUEVO: Cerrar sesión en la base de datos ANTES de destruir sesión PHP
            try {
                SessionTracker::closeSession('logout');
            } catch (Exception $e) {
                error_log("Error closing session in database: " . $e->getMessage());
            }
            
            $success = $this->logoutService->logout();
            if ($success) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Sesión cerrada correctamente',
                ]);    
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => 'No se pudo cerrar la sesión',
                ]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        }
    }
}
?>