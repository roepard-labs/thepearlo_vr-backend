<?php
// Requiere el conexion a la base de datos
require_once __DIR__ . '/../core/db.php';

// Clase UserUpdate
class UserUpdate {
    private $db;

    // Crea una nueva instancia
    public function __construct() {
        $dbConfig = new DBConfig();
        $this->db = $dbConfig->getConnection();
    }

    // Actualiza un usuario
    public function update($userData) {
        try {
            $sql = "UPDATE users SET 
                profile_picture = :profile_picture,
                first_name = :first_name, 
                last_name = :last_name,
                username = :username, 
                email = :email, 
                phone = :phone,
                password = :password,
                country = :country,
                city = :city,
                birthdate = :birthdate,
                status_id = :status_id, 
                role_id = :role_id
                WHERE user_id = :user_id";

            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute([
                ':profile_picture' => $userData['profile_picture'],
                ':first_name' => $userData['first_name'],
                ':last_name' => $userData['last_name'],
                ':username' => $userData['username'],
                ':email' => $userData['email'],
                ':phone' => $userData['phone'],
                ':password' => $userData['password'],
                ':country' => $userData['country'],
                ':city' => $userData['city'],
                ':birthdate' => $userData['birthdate'],
                ':status_id' => $userData['status_id'],
                ':role_id' => $userData['role_id'],
                ':user_id' => $userData['user_id']
            ]);
        } catch (PDOException $e) {
            throw new Exception("Error al actualizar usuario: " . $e->getMessage());
        }
    }

        public function verifyCurrentPassword($userId, $currentPassword) {
        $sql = "SELECT password FROM users WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user && password_verify($currentPassword, $user['password']);
    }

    // Obtiene la contraseña actual del usuario
    public function getCurrentPassword($userId) {
        $sql = "SELECT password FROM users WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user['password'];
    }

    // Obtiene la foto de perfil del usuario
    public function getProfilePicture($userId) {
        $sql = "SELECT profile_picture FROM users WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user['profile_picture'] ?? 'default-profile.png';
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