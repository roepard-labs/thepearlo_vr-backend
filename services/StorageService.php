<?php
/**
 * Servicio de Almacenamiento - Manejo físico de archivos
 * Lógica de negocio para filesystem
 * HomeLab AR - Roepard Labs
 */

class StorageService
{
    private $baseStoragePath;
    private $maxFileSize; // En bytes
    private $allowedExtensions;

    public function __construct()
    {
        $this->baseStoragePath = __DIR__ . '/../storage/app/private/';
        $this->maxFileSize = 52428800; // 50 MB por defecto
        $this->allowedExtensions = [
            // Imágenes
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp',
            'svg',
            'bmp',
            'ico',
            // Documentos
            'pdf',
            'doc',
            'docx',
            'xls',
            'xlsx',
            'ppt',
            'pptx',
            'txt',
            'rtf',
            'odt',
            // Comprimidos
            'zip',
            'rar',
            '7z',
            'tar',
            'gz',
            // Videos
            'mp4',
            'avi',
            'mov',
            'wmv',
            'flv',
            'mkv',
            'webm',
            // Audio
            'mp3',
            'wav',
            'ogg',
            'flac',
            'aac',
            'm4a',
            // Modelos 3D (para VR/AR)
            'gltf',
            'glb',
            'obj',
            'fbx',
            'dae',
            'stl',
            'ply',
            // Código
            'js',
            'css',
            'html',
            'php',
            'json',
            'xml',
            'yml',
            'yaml',
            // Otros
            'csv',
            'sql',
            'md'
        ];
    }

    /**
     * Validar archivo subido
     * Retorna array con ['valid' => bool, 'message' => string, 'data' => array]
     */
    public function validateUpload($file): array
    {
        // Verificar que el archivo existe
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return [
                'valid' => false,
                'message' => 'No se recibió ningún archivo',
                'data' => null
            ];
        }

        // Verificar errores de upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'valid' => false,
                'message' => $this->getUploadErrorMessage($file['error']),
                'data' => null
            ];
        }

        // Verificar tamaño
        if ($file['size'] > $this->maxFileSize) {
            return [
                'valid' => false,
                'message' => 'El archivo excede el tamaño máximo permitido (50 MB)',
                'data' => null
            ];
        }

        // Verificar extensión
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            return [
                'valid' => false,
                'message' => "Tipo de archivo no permitido: .{$extension}",
                'data' => null
            ];
        }

        // Validar MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        return [
            'valid' => true,
            'message' => 'Archivo válido',
            'data' => [
                'original_name' => $file['name'],
                'size' => $file['size'],
                'mime_type' => $mimeType,
                'extension' => $extension
            ]
        ];
    }

    /**
     * Guardar archivo en el sistema de archivos
     */
    public function saveFile($file, $userId): array
    {
        try {
            // Validar primero
            $validation = $this->validateUpload($file);
            if (!$validation['valid']) {
                return [
                    'status' => 'error',
                    'message' => $validation['message']
                ];
            }

            // Crear directorio del usuario si no existe
            $userDir = $this->baseStoragePath . "user_{$userId}/";
            if (!is_dir($userDir)) {
                mkdir($userDir, 0755, true);
            }

            // Generar nombre único para el archivo
            $extension = $validation['data']['extension'];
            $uniqueName = $this->generateUniqueFileName($extension);
            $targetPath = $userDir . $uniqueName;

            // Mover archivo
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                return [
                    'status' => 'error',
                    'message' => 'Error al guardar el archivo en el servidor'
                ];
            }

            // Cambiar permisos
            chmod($targetPath, 0644);

            return [
                'status' => 'success',
                'message' => 'Archivo guardado exitosamente',
                'data' => [
                    'file_name' => $uniqueName,
                    'original_name' => $validation['data']['original_name'],
                    'file_path' => "user_{$userId}/{$uniqueName}",
                    'file_size' => $validation['data']['size'],
                    'file_type' => $validation['data']['mime_type'],
                    'file_extension' => $extension
                ]
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error al procesar archivo: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar archivo físico del servidor
     */
    public function deleteFile($filePath): bool
    {
        try {
            $fullPath = $this->baseStoragePath . $filePath;

            if (file_exists($fullPath)) {
                return unlink($fullPath);
            }

            return false;
        } catch (Exception $e) {
            throw new Exception("Error al eliminar archivo: " . $e->getMessage());
        }
    }

    /**
     * Obtener ruta completa del archivo
     */
    public function getFullPath($filePath): string
    {
        return $this->baseStoragePath . $filePath;
    }

    /**
     * Verificar si el archivo existe
     */
    public function fileExists($filePath): bool
    {
        $fullPath = $this->baseStoragePath . $filePath;
        return file_exists($fullPath);
    }

    /**
     * Obtener tipo de archivo por extensión
     */
    public function getFileTypeCategory($extension): string
    {
        $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'];
        $documentTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'odt'];
        $videoTypes = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm'];
        $audioTypes = ['mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a'];
        $archiveTypes = ['zip', 'rar', '7z', 'tar', 'gz'];
        $modelTypes = ['gltf', 'glb', 'obj', 'fbx', 'dae', 'stl', 'ply'];

        $ext = strtolower($extension);

        if (in_array($ext, $imageTypes))
            return 'image';
        if (in_array($ext, $documentTypes))
            return 'document';
        if (in_array($ext, $videoTypes))
            return 'video';
        if (in_array($ext, $audioTypes))
            return 'audio';
        if (in_array($ext, $archiveTypes))
            return 'archive';
        if (in_array($ext, $modelTypes))
            return 'model';

        return 'other';
    }

    /**
     * Formatear tamaño de archivo
     */
    public function formatFileSize($bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    /**
     * Generar nombre único para archivo
     */
    private function generateUniqueFileName($extension): string
    {
        return uniqid('file_', true) . '_' . time() . '.' . $extension;
    }

    /**
     * Obtener mensaje de error de upload
     */
    private function getUploadErrorMessage($errorCode): string
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por PHP',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal en el servidor',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en disco',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida'
        ];

        return $errors[$errorCode] ?? 'Error desconocido al subir archivo';
    }

    /**
     * Crear directorio de usuario con carpetas por defecto
     * Se ejecuta al registrarse y al iniciar sesión (verificación)
     * 
     * @param int $userId ID del usuario
     * @param array|null $folderNames Array de nombres de carpetas a crear (opcional)
     * @return array Resultado de la operación
     */
    public function createUserDirectory($userId, $folderNames = null): array
    {
        try {
            // Ruta base del usuario
            $userDir = $this->baseStoragePath . "user_{$userId}/";

            // Crear directorio principal del usuario si no existe
            if (!is_dir($userDir)) {
                if (!mkdir($userDir, 0755, true)) {
                    return [
                        'status' => 'error',
                        'message' => "No se pudo crear el directorio del usuario {$userId}",
                        'created_folders' => []
                    ];
                }
                chmod($userDir, 0755);
                error_log("✅ Directorio creado: {$userDir}");
            }

            // Si no se especifican carpetas, usar las predeterminadas
            if ($folderNames === null) {
                $folderNames = ['Documentos', 'Música', 'Videos', 'Imágenes'];
            }

            $createdFolders = [];
            $skippedFolders = [];

            // Crear subcarpetas predeterminadas
            foreach ($folderNames as $folderName) {
                $folderPath = $userDir . $folderName . '/';

                if (!is_dir($folderPath)) {
                    if (mkdir($folderPath, 0755, true)) {
                        chmod($folderPath, 0755);
                        $createdFolders[] = $folderName;
                        error_log("✅ Subcarpeta creada: {$folderPath}");
                    } else {
                        error_log("❌ Error al crear subcarpeta: {$folderPath}");
                    }
                } else {
                    $skippedFolders[] = $folderName;
                }
            }

            return [
                'status' => 'success',
                'message' => 'Estructura de carpetas verificada/creada correctamente',
                'user_dir' => $userDir,
                'created_folders' => $createdFolders,
                'skipped_folders' => $skippedFolders,
                'total_folders' => count($folderNames)
            ];

        } catch (Exception $e) {
            error_log("❌ Error al crear estructura de carpetas para usuario {$userId}: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Error al crear estructura de carpetas: ' . $e->getMessage(),
                'created_folders' => []
            ];
        }
    }

    /**
     * Verificar y sincronizar carpetas físicas con base de datos
     * Útil para ejecutar al iniciar sesión como verificación
     * 
     * @param int $userId ID del usuario
     * @param array $dbFolders Array de carpetas desde la BD
     * @return array Resultado de la sincronización
     */
    public function syncUserFolders($userId, $dbFolders): array
    {
        try {
            $userDir = $this->baseStoragePath . "user_{$userId}/";

            // Verificar que el directorio del usuario exista
            if (!is_dir($userDir)) {
                // Si no existe, crear toda la estructura
                return $this->createUserDirectory($userId, array_column($dbFolders, 'folder_name'));
            }

            $createdFolders = [];
            $existingFolders = [];

            // Sincronizar cada carpeta de la BD con el filesystem
            foreach ($dbFolders as $folder) {
                $folderName = $folder['folder_name'];
                $folderPath = $userDir . $folderName . '/';

                if (!is_dir($folderPath)) {
                    if (mkdir($folderPath, 0755, true)) {
                        chmod($folderPath, 0755);
                        $createdFolders[] = $folderName;
                        error_log("🔄 Carpeta sincronizada: {$folderPath}");
                    }
                } else {
                    $existingFolders[] = $folderName;
                }
            }

            return [
                'status' => 'success',
                'message' => 'Carpetas sincronizadas correctamente',
                'created' => $createdFolders,
                'existing' => $existingFolders,
                'total' => count($dbFolders)
            ];

        } catch (Exception $e) {
            error_log("❌ Error al sincronizar carpetas para usuario {$userId}: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Error al sincronizar carpetas: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar directorio de usuario (solo si está vacío)
     */
    public function deleteUserDirectory($userId): bool
    {
        $userDir = $this->baseStoragePath . "user_{$userId}/";

        if (is_dir($userDir) && count(scandir($userDir)) === 2) { // Solo . y ..
            return rmdir($userDir);
        }

        return false;
    }
}
