<?php
/**
 * Upload Profile Picture
 * POST /routes/profile/upload_picture.php
 * 
 * Subir o actualizar foto de perfil del usuario autenticado
 * Máximo: 5MB
 * Formatos: JPG, JPEG, PNG, GIF, WEBP
 * HomeLab AR - Roepard Labs
 */

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../middleware/user.php';
require_once __DIR__ . '/../../middleware/status.php';

// Solo POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Método no permitido. Use POST."
    ]);
    exit;
}

// Verificar autenticación
Auth::checkAuth();
Status::checkStatus(1); // Usuario debe estar activo

$userId = $_SESSION['user_id'];

try {
    // Configuración
    $uploadDir = __DIR__ . '/../../uploads/profiles/';
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    // Verificar que se subió un archivo
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception("No se recibió ninguna imagen");
    }

    $file = $_FILES['profile_picture'];

    // Verificar errores de upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => "El archivo excede el tamaño máximo permitido por el servidor",
            UPLOAD_ERR_FORM_SIZE => "El archivo excede el tamaño máximo permitido",
            UPLOAD_ERR_PARTIAL => "El archivo se subió parcialmente",
            UPLOAD_ERR_NO_FILE => "No se subió ningún archivo",
            UPLOAD_ERR_NO_TMP_DIR => "Falta la carpeta temporal",
            UPLOAD_ERR_CANT_WRITE => "Error al escribir el archivo en disco",
            UPLOAD_ERR_EXTENSION => "Una extensión de PHP detuvo la subida del archivo"
        ];

        $errorMsg = $errorMessages[$file['error']] ?? "Error desconocido al subir archivo";
        throw new Exception($errorMsg);
    }

    // Verificar tamaño
    if ($file['size'] > $maxFileSize) {
        throw new Exception("La imagen excede el tamaño máximo de 5MB");
    }

    // Verificar tipo MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception("Formato de imagen no permitido. Solo JPG, PNG, GIF y WEBP");
    }

    // Verificar extensión
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        throw new Exception("Extensión de archivo no permitida");
    }

    // Crear directorio si no existe
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception("No se pudo crear el directorio de uploads");
        }
    }

    // Obtener foto de perfil anterior (para eliminarla)
    $dbConfig = new DBConfig();
    $db = $dbConfig->getConnection();
    $sqlOld = "SELECT profile_picture FROM users WHERE user_id = :user_id";
    $stmtOld = $db->prepare($sqlOld);
    $stmtOld->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmtOld->execute();
    $oldPicture = $stmtOld->fetchColumn();

    // Generar nombre único para la imagen
    $fileName = 'profile_' . $userId . '_' . time() . '.' . $extension;
    $filePath = $uploadDir . $fileName;

    // Mover archivo
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception("Error al guardar la imagen en el servidor");
    }

    // Ruta relativa para guardar en BD
    $relativePath = '/uploads/profiles/' . $fileName;

    // Actualizar en base de datos
    $sqlUpdate = "UPDATE users SET profile_picture = :profile_picture, updated_at = NOW() WHERE user_id = :user_id";
    $stmtUpdate = $db->prepare($sqlUpdate);
    $stmtUpdate->bindParam(':profile_picture', $relativePath, PDO::PARAM_STR);
    $stmtUpdate->bindParam(':user_id', $userId, PDO::PARAM_INT);

    if (!$stmtUpdate->execute()) {
        // Si falla la BD, eliminar archivo subido
        unlink($filePath);
        throw new Exception("Error al actualizar la foto de perfil en la base de datos");
    }

    // Eliminar foto anterior si existe y no es la por defecto
    if ($oldPicture && $oldPicture !== '/assets/img/default-avatar.png') {
        $oldFilePath = __DIR__ . '/../../' . ltrim($oldPicture, '/');
        if (file_exists($oldFilePath)) {
            unlink($oldFilePath);
        }
    }

    // Respuesta exitosa
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Foto de perfil actualizada exitosamente",
        "data" => [
            "profile_picture" => $relativePath,
            "file_name" => $fileName,
            "file_size" => $file['size'],
            "mime_type" => $mimeType
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>