<?php
/**
 * Delete Profile Picture
 * DELETE /routes/profile/delete_picture.php
 * 
 * Eliminar foto de perfil del usuario autenticado
 * Restaura la imagen por defecto
 * HomeLab AR - Roepard Labs
 */

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../middleware/user.php';
require_once __DIR__ . '/../../middleware/status.php';

// Solo DELETE
if ($_SERVER["REQUEST_METHOD"] !== "DELETE") {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Método no permitido. Use DELETE."
    ]);
    exit;
}

// Verificar autenticación
Auth::checkAuth();
Status::checkStatus(1); // Usuario debe estar activo

$userId = $_SESSION['user_id'];

try {
    $dbConfig = new DBConfig();
    $db = $dbConfig->getConnection();

    // Obtener foto de perfil actual
    $sqlCurrent = "SELECT profile_picture FROM users WHERE user_id = :user_id";
    $stmtCurrent = $db->prepare($sqlCurrent);
    $stmtCurrent->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmtCurrent->execute();
    $currentPicture = $stmtCurrent->fetchColumn();

    // Verificar si tiene foto personalizada
    if (!$currentPicture || $currentPicture === '/assets/img/default-avatar.png') {
        throw new Exception("No tienes una foto de perfil personalizada para eliminar");
    }

    // Ruta por defecto
    $defaultPicture = '/assets/img/default-avatar.png';

    // Actualizar en base de datos
    $sqlUpdate = "UPDATE users SET profile_picture = :default_picture, updated_at = NOW() WHERE user_id = :user_id";
    $stmtUpdate = $db->prepare($sqlUpdate);
    $stmtUpdate->bindParam(':default_picture', $defaultPicture, PDO::PARAM_STR);
    $stmtUpdate->bindParam(':user_id', $userId, PDO::PARAM_INT);

    if (!$stmtUpdate->execute()) {
        throw new Exception("Error al eliminar la foto de perfil en la base de datos");
    }

    // Eliminar archivo físico
    $filePath = __DIR__ . '/../../' . ltrim($currentPicture, '/');
    if (file_exists($filePath)) {
        if (!unlink($filePath)) {
            // No es crítico, la BD ya se actualizó
            error_log("No se pudo eliminar el archivo físico: " . $filePath);
        }
    }

    // Respuesta exitosa
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Foto de perfil eliminada exitosamente",
        "data" => [
            "profile_picture" => $defaultPicture
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