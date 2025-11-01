<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../models/UserRegister.php';

class RegisterService {
    public function register(UserRegister $user) {
        $dbConfig = new DBconfig();
        $db = $dbConfig->getConnection();

        // Validar email
        if (!filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'error', 'message' => 'Invalid email format'];
        }

        // Encriptar contraseña
        $hashed_password = password_hash($user->password, PASSWORD_BCRYPT);

        // Verificar si el email, username o phone ya existen
        $query = "SELECT * FROM users WHERE email = :email OR username = :username OR phone = :phone";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':email' => $user->email,
            ':username' => $user->username,
            ':phone' => $user->phone
        ]);
        if ($stmt->rowCount() > 0) {
            return ['status' => 'error', 'message' => 'Email, username or phone already in use.'];
        }

        // Insertar nuevo usuario
        $query = "INSERT INTO users (first_name, last_name, phone, username, email, password) VALUES (:first_name, :last_name, :phone, :username, :email, :password)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':first_name' => $user->first_name,
            ':last_name' => $user->last_name,
            ':phone' => $user->phone,
            ':username' => $user->username,
            ':email' => $user->email,
            ':password' => $hashed_password
        ]);

        $user_id = $db->lastInsertId();
        return ['status' => 'success', 'message' => 'User registered successfully.', 'user_id' => $user_id];
    }
}
