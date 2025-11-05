<?php
/**
 * Controlador de Archivos - Maneja HTTP y coordina servicios
 * Siguiendo el patrón MVC estricto
 * HomeLab AR - Roepard Labs
 */

require_once __DIR__ . '/../services/FileService.php';
require_once __DIR__ . '/../middleware/user.php';

class FileController
{
    private $fileService;

    public function __construct()
    {
        $this->fileService = new FileService();
    }

    /**
     * Subir archivo
     * POST /routes/files/upload_file.php
     */
    public function upload(): void
    {
        // Validar método HTTP
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendResponse(['error' => 'Método no permitido'], 405);
            return;
        }

        // Verificar autenticación
        $authCheck = Auth::checkAuth();
        if (!$authCheck) {
            $this->sendResponse(['error' => 'No autenticado'], 401);
            return;
        }

        $userId = $_SESSION['user_id'];
        $isAdmin = isset($_SESSION['role_id']) && $_SESSION['role_id'] == 2;

        try {
            // DEBUG: Ver qué se recibió
            error_log('📦 Upload Debug - $_FILES: ' . print_r($_FILES, true));
            error_log('📦 Upload Debug - $_POST: ' . print_r($_POST, true));

            // Validar que se recibió archivo
            if (!isset($_FILES['file']) || empty($_FILES['file']['tmp_name'])) {
                $this->sendResponse([
                    'status' => 'error',
                    'message' => 'No se recibió ningún archivo',
                    'debug' => [
                        'files_isset' => isset($_FILES['file']),
                        'files_keys' => array_keys($_FILES),
                        'post_keys' => array_keys($_POST)
                    ]
                ], 400);
                return;
            }

            // Obtener datos adicionales
            $folderId = $_POST['folder_id'] ?? null;
            $description = $_POST['description'] ?? '';
            $isShared = isset($_POST['is_shared']) ? (int) $_POST['is_shared'] : 0;

            // CRÍTICO: Validar que se especifique una carpeta
            // No se permite subir archivos en root (folder_id null/vacío)
            if (empty($folderId) || $folderId === 'root') {
                error_log('❌ Intento de subir archivo en root - PROHIBIDO');
                $this->sendResponse([
                    'status' => 'error',
                    'message' => 'No se pueden subir archivos en la raíz. Debes seleccionar una carpeta (Documentos, Música, Videos, Imágenes).'
                ], 400);
                return;
            }

            // SEGURIDAD: Verificar permisos sobre la carpeta
            // Solo admin puede subir a carpetas de otros usuarios
            if (!$isAdmin) {
                require_once __DIR__ . '/../models/Folder.php';
                $folderModel = new Folder();
                $folderData = $folderModel->findById($folderId);

                if (!$folderData) {
                    error_log('❌ Carpeta no encontrada: ' . $folderId);
                    $this->sendResponse([
                        'status' => 'error',
                        'message' => 'La carpeta seleccionada no existe.'
                    ], 404);
                    return;
                }

                if ($folderData['user_id'] != $userId) {
                    error_log('❌ Usuario ' . $userId . ' intentó subir archivo a carpeta ajena: ' . $folderId);
                    $this->sendResponse([
                        'status' => 'error',
                        'message' => 'No tienes permiso para subir archivos a esta carpeta.'
                    ], 403);
                    return;
                }
            }

            // Si no es admin, no puede compartir archivos
            if (!$isAdmin) {
                $isShared = 0;
            }

            // Procesar subida
            $result = $this->fileService->uploadFile(
                $_FILES['file'],
                $userId,
                $folderId,
                $description,
                $isShared
            );

            $statusCode = $result['status'] === 'success' ? 200 : 400;
            $this->sendResponse($result, $statusCode);

        } catch (Exception $e) {
            $this->sendResponse([
                'status' => 'error',
                'message' => 'Error del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar archivos de usuario o carpeta
     * GET /routes/files/list_files.php?folder_id=X
     */
    public function listFiles(): void
    {
        // Verificar autenticación
        $authCheck = Auth::checkAuth();
        if (!$authCheck) {
            $this->sendResponse(['error' => 'No autenticado'], 401);
            return;
        }

        $userId = $_SESSION['user_id'];
        $isAdmin = isset($_SESSION['role_id']) && $_SESSION['role_id'] == 2;

        // DEBUG: Ver qué hay en la sesión
        error_log("🔐 FileController::listFiles() - user_id: " . $userId);
        error_log("🔐 FileController::listFiles() - role_id en sesión: " . ($_SESSION['role_id'] ?? 'NO EXISTE'));
        error_log("🔐 FileController::listFiles() - isAdmin calculado: " . ($isAdmin ? 'true' : 'false'));
        error_log("📦 FileController::listFiles() - Sesión completa: " . print_r($_SESSION, true));

        try {
            $folderId = $_GET['folder_id'] ?? null;

            // Convertir 'root' a null para consultas de nivel raíz
            if ($folderId === 'root' || $folderId === '') {
                $folderId = null;
            }

            $result = $this->fileService->getUserFiles($userId, $folderId, $isAdmin);
            $this->sendResponse($result);

        } catch (Exception $e) {
            $this->sendResponse([
                'status' => 'error',
                'message' => 'Error del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener detalles de un archivo
     * GET /routes/files/get_file.php?file_id=X
     */
    public function getFile(): void
    {
        // Verificar autenticación
        $authCheck = Auth::checkAuth();
        if (!$authCheck) {
            $this->sendResponse(['error' => 'No autenticado'], 401);
            return;
        }

        $userId = $_SESSION['user_id'];
        $isAdmin = isset($_SESSION['role_id']) && $_SESSION['role_id'] == 2;

        try {
            $fileId = $_GET['file_id'] ?? null;

            if (!$fileId) {
                $this->sendResponse([
                    'status' => 'error',
                    'message' => 'ID de archivo requerido'
                ], 400);
                return;
            }

            $result = $this->fileService->getFileDetails($fileId, $userId, $isAdmin);
            $this->sendResponse($result);

        } catch (Exception $e) {
            $this->sendResponse([
                'status' => 'error',
                'message' => 'Error del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar archivo
     * PUT /routes/files/update_file.php
     */
    public function update(): void
    {
        // Verificar autenticación
        $authCheck = Auth::checkAuth();
        if (!$authCheck) {
            $this->sendResponse(['error' => 'No autenticado'], 401);
            return;
        }

        $userId = $_SESSION['user_id'];
        $isAdmin = isset($_SESSION['role_id']) && $_SESSION['role_id'] == 2;

        try {
            // Obtener datos JSON
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['file_id'])) {
                $this->sendResponse([
                    'status' => 'error',
                    'message' => 'ID de archivo requerido'
                ], 400);
                return;
            }

            $result = $this->fileService->updateFile(
                $input['file_id'],
                $userId,
                $input,
                $isAdmin
            );

            $statusCode = $result['status'] === 'success' ? 200 : 400;
            $this->sendResponse($result, $statusCode);

        } catch (Exception $e) {
            $this->sendResponse([
                'status' => 'error',
                'message' => 'Error del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar archivo
     * DELETE /routes/files/delete_file.php
     */
    public function delete(): void
    {
        // Verificar autenticación
        $authCheck = Auth::checkAuth();
        if (!$authCheck) {
            $this->sendResponse(['error' => 'No autenticado'], 401);
            return;
        }

        $userId = $_SESSION['user_id'];
        $isAdmin = isset($_SESSION['role_id']) && $_SESSION['role_id'] == 2;

        try {
            // Obtener datos JSON o query params
            $fileId = $_GET['file_id'] ?? null;
            $type = $_GET['type'] ?? 'file'; // Por defecto, asumimos que es archivo

            if (!$fileId) {
                $input = json_decode(file_get_contents('php://input'), true);
                $fileId = $input['file_id'] ?? null;
                $type = $input['type'] ?? 'file';
            }

            if (!$fileId) {
                $this->sendResponse([
                    'status' => 'error',
                    'message' => 'ID de archivo requerido'
                ], 400);
                return;
            }

            // CRÍTICO: Determinar si es carpeta o archivo
            $isFolder = ($type === 'folder');

            if ($isFolder) {
                // Eliminar carpeta
                $result = $this->fileService->deleteFolder($fileId, $userId, $isAdmin);
            } else {
                // Eliminar archivo
                $result = $this->fileService->deleteFile($fileId, $userId, $isAdmin);
            }

            $statusCode = $result['status'] === 'success' ? 200 : 400;
            $this->sendResponse($result, $statusCode);

        } catch (Exception $e) {
            $this->sendResponse([
                'status' => 'error',
                'message' => 'Error del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descargar archivo
     * GET /routes/files/download_file.php?file_id=X
     */
    public function download(): void
    {
        // Verificar autenticación
        $authCheck = Auth::checkAuth();
        if (!$authCheck) {
            $this->sendResponse(['error' => 'No autenticado'], 401);
            return;
        }

        $userId = $_SESSION['user_id'];
        $isAdmin = isset($_SESSION['role_id']) && $_SESSION['role_id'] == 2;

        try {
            $fileId = $_GET['file_id'] ?? null;

            if (!$fileId) {
                $this->sendResponse([
                    'status' => 'error',
                    'message' => 'ID de archivo requerido'
                ], 400);
                return;
            }

            $result = $this->fileService->prepareDownload($fileId, $userId, $isAdmin);

            if ($result['status'] === 'error') {
                $this->sendResponse($result, 400);
                return;
            }

            // Verificar si es inline (para preview) o descarga
            $inline = isset($_GET['inline']) && $_GET['inline'] == '1';

            // Enviar archivo
            $this->sendFileDownload(
                $result['file_path'],
                $result['original_name'],
                $result['file_type'],
                $inline
            );

        } catch (Exception $e) {
            $this->sendResponse([
                'status' => 'error',
                'message' => 'Error del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de almacenamiento
     * GET /routes/files/get_stats.php
     */
    public function getStats(): void
    {
        // Verificar autenticación
        $authCheck = Auth::checkAuth();
        if (!$authCheck) {
            $this->sendResponse(['error' => 'No autenticado'], 401);
            return;
        }

        $userId = $_SESSION['user_id'];

        try {
            $result = $this->fileService->getUserStats($userId);
            $this->sendResponse($result);

        } catch (Exception $e) {
            $this->sendResponse([
                'status' => 'error',
                'message' => 'Error del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar archivos
     * GET /routes/files/search_files.php?q=termino
     */
    public function search(): void
    {
        // Verificar autenticación
        $authCheck = Auth::checkAuth();
        if (!$authCheck) {
            $this->sendResponse(['error' => 'No autenticado'], 401);
            return;
        }

        $userId = $_SESSION['user_id'];
        $isAdmin = isset($_SESSION['role_id']) && $_SESSION['role_id'] == 2;

        try {
            $searchTerm = $_GET['q'] ?? '';

            if (empty($searchTerm)) {
                $this->sendResponse([
                    'status' => 'error',
                    'message' => 'Término de búsqueda requerido'
                ], 400);
                return;
            }

            $result = $this->fileService->searchFiles($userId, $searchTerm, $isAdmin);
            $this->sendResponse($result);

        } catch (Exception $e) {
            $this->sendResponse([
                'status' => 'error',
                'message' => 'Error del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar respuesta JSON
     */
    private function sendResponse($data, $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Enviar archivo para descarga
     */
    private function sendFileDownload($filePath, $originalName, $mimeType, $inline = false): void
    {
        if (!file_exists($filePath)) {
            $this->sendResponse([
                'status' => 'error',
                'message' => 'Archivo no encontrado'
            ], 404);
            return;
        }

        // Headers según modo (inline o attachment)
        header('Content-Type: ' . $mimeType);

        // CRÍTICO: Si es inline, mostrar en navegador; sino, descargar
        if ($inline) {
            header('Content-Disposition: inline; filename="' . $originalName . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $originalName . '"');
        }

        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: max-age=3600'); // Cache por 1 hora para inline

        // Limpiar buffer
        if (ob_get_length()) {
            ob_clean();
        }
        flush();

        // Enviar archivo
        readfile($filePath);
        exit;
    }

    /**
     * Crear carpeta
     * POST /routes/files/create_folder.php
     */
    public function createFolder(): void
    {
        // Validar método HTTP
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendResponse(['error' => 'Método no permitido'], 405);
            return;
        }

        // Verificar autenticación
        $authCheck = Auth::checkAuth();
        if (!$authCheck) {
            $this->sendResponse(['error' => 'No autenticado'], 401);
            return;
        }

        $userId = $_SESSION['user_id'];

        try {
            // Obtener datos del request
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            // Validar datos requeridos
            if (empty($data['name'])) {
                $this->sendResponse([
                    'status' => 'error',
                    'message' => 'El nombre de la carpeta es obligatorio'
                ], 400);
                return;
            }

            // Preparar datos para el servicio
            $folderData = [
                'name' => $data['name'],
                'parent_folder' => $data['parent_folder'] ?? null,
                'description' => $data['description'] ?? '',
                'user_id' => $userId
            ];

            // Llamar al servicio para crear la carpeta
            $result = $this->fileService->createFolder($folderData);

            $statusCode = $result['status'] === 'success' ? 201 : 400;
            $this->sendResponse($result, $statusCode);

        } catch (Exception $e) {
            $this->sendResponse([
                'status' => 'error',
                'message' => 'Error del servidor: ' . $e->getMessage()
            ], 500);
        }
    }
}
