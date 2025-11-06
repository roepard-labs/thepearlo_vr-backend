<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../models/UserRegister.php';

class RegisterService
{
    public function register(UserRegister $user)
    {
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

        // Iniciar transacción para crear usuario y carpetas
        $db->beginTransaction();

        try {
            // Insertar nuevo usuario con foto de perfil por defecto
            $query = "INSERT INTO users (first_name, last_name, phone, username, email, password, profile_picture) VALUES (:first_name, :last_name, :phone, :username, :email, :password, :profile_picture)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':first_name' => $user->first_name,
                ':last_name' => $user->last_name,
                ':phone' => $user->phone,
                ':username' => $user->username,
                ':email' => $user->email,
                ':password' => $hashed_password,
                ':profile_picture' => '/assets/img/default-avatar.png'
            ]);

            $user_id = $db->lastInsertId();

            // Crear carpetas fijas para el usuario
            $defaultFolders = [
                ['name' => 'Documentos', 'description' => 'Documentos y archivos importantes'],
                ['name' => 'Música', 'description' => 'Archivos de audio y música'],
                ['name' => 'Videos', 'description' => 'Archivos de video'],
                ['name' => 'Imágenes', 'description' => 'Fotos e imágenes']
            ];

            $folderQuery = "INSERT INTO folders (user_id, folder_name, folder_path, description, parent_folder_id) VALUES (:user_id, :folder_name, :folder_path, :description, NULL)";
            $folderStmt = $db->prepare($folderQuery);

            foreach ($defaultFolders as $folder) {
                $folderStmt->execute([
                    ':user_id' => $user_id,
                    ':folder_name' => $folder['name'],
                    ':folder_path' => '/' . $folder['name'],
                    ':description' => $folder['description']
                ]);
            }

            // ✅ NUEVO: Crear carpetas físicas en el sistema de archivos
            require_once __DIR__ . '/StorageService.php';
            $storageService = new StorageService();

            $folderNames = array_column($defaultFolders, 'name');
            $storageResult = $storageService->createUserDirectory($user_id, $folderNames);

            if ($storageResult['status'] === 'error') {
                // Si falla la creación física, hacer rollback
                $db->rollBack();
                error_log("❌ Error al crear carpetas físicas: " . $storageResult['message']);
                return [
                    'status' => 'error',
                    'message' => 'Error creating user storage structure.'
                ];
            }

            // Commit de la transacción
            $db->commit();

            error_log("✅ Usuario registrado con ID: $user_id - Carpetas BD: " . count($defaultFolders) . " | Carpetas físicas: " . count($storageResult['created_folders']));

            return [
                'status' => 'success',
                'message' => 'User registered successfully with default folders.',
                'user_id' => $user_id,
                'folders_created' => [
                    'database' => count($defaultFolders),
                    'filesystem' => $storageResult['created_folders']
                ]
            ];

        } catch (Exception $e) {
            // Rollback en caso de error
            $db->rollBack();
            error_log("❌ Error al registrar usuario: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error creating user account.'];
        }
    }
}
