<?php
require_once __DIR__ . '/../services/RegisterService.php';
require_once __DIR__ . '/../models/UserRegister.php';
require_once __DIR__ . '/../core/session.php';

class RegisterController {
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            return;
        }

        $required_fields = ['first_name', 'last_name', 'phone', 'username', 'email', 'password'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => "Missing required field: $field"]);
                return;
            }
        }

        $data = [
            'first_name' => filter_var($_POST['first_name'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            'last_name' => filter_var($_POST['last_name'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            'phone' => preg_replace('/[^0-9+]/', '', $_POST['phone']),
            'username' => filter_var($_POST['username'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            'email' => filter_var($_POST['email'], FILTER_SANITIZE_EMAIL),
            'password' => $_POST['password']
        ];

        // Validación básica de contraseña (ampliable)
        if (strlen($data['password']) < 8) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters']);
            return;
        }

        $user = new UserRegister($data);
        $service = new RegisterService();
        $result = $service->register($user);

        if ($result['status'] === 'success') {
            ensure_session_started();
            session_regenerate_once();
            $_SESSION['user_id'] = $result['user_id'];
            $_SESSION['username'] = $user->username;
        }

        echo json_encode($result);
    }
}
