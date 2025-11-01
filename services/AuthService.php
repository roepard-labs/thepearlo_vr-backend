<?php
/**
 * Servicio de Autenticación - Solo lógica de negocio
 * NO maneja sesiones ni acceso directo a datos
 */

require_once __DIR__ . '/../models/UserAuth.php';

class AuthService {
    private $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    /**
     * Valida las credenciales del usuario
     * Solo lógica de negocio - NO maneja sesiones
     */
    public function validateCredentials($input, $password): array {
        // Validar entrada
        if (empty($input) || empty($password)) {
            return [
                'status' => 'error',
                'message' => 'Credenciales incompletas',
                'user_data' => null
            ];
        }

        try {
            // Obtener usuario del modelo
            $user = $this->userModel->findByCredentials($input);
            
            // Verificar si el usuario existe
            if (!$user) {
                return [
                    'status' => 'error',
                    'message' => 'Credenciales incorrectas',
                    'user_data' => null
                ];
            }

            // Verificar contraseña
            if (!password_verify($password, $user['password'])) {
                return [
                    'status' => 'error',
                    'message' => 'Credenciales incorrectas',
                    'user_data' => null
                ];
            }

            // Verificar que el usuario esté activo
            if (!isset($user['status_id']) || (int)$user['status_id'] !== 1) {
                return [
                    'status' => 'error',
                    'message' => 'Usuario deshabilitado o sin permisos',
                    'user_data' => null
                ];
            }

            // Rehash si es necesario
            $this->handlePasswordRehash($user, $password);

            // Limpiar datos sensibles antes de devolver
            unset($user['password']);

            return [
                'status' => 'success',
                'message' => 'Credenciales válidas',
                'user_data' => $user
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error interno del servidor',
                'user_data' => null
            ];
        }
    }

    /**
     * Maneja el rehash de contraseñas cuando es necesario
     * Lógica de negocio pura
     */
    private function handlePasswordRehash($user, $password): void {
        if (password_needs_rehash($user['password'], PASSWORD_BCRYPT)) {
            $newHash = password_hash($password, PASSWORD_BCRYPT);
            try {
                $this->userModel->updatePassword($user['user_id'], $newHash);
            } catch (Exception $e) {
                // Log error pero no bloquea el login
                error_log("Error al rehash contraseña para usuario {$user['user_id']}: " . $e->getMessage());
            }
        }
    }

    /**
     * Prepara datos del usuario para la sesión
     * Solo lógica de negocio - no maneja la sesión directamente
     */
    public function prepareUserSessionData($userData): array {
        return [
            'user_id' => $userData['user_id'],
            'first_name' => $userData['first_name'],
            'last_name' => $userData['last_name'],
            'email' => $userData['email'],
            'phone' => $userData['phone'],
            'status_id' => $userData['status_id']
        ];
    }
}
?>