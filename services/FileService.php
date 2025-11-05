<?php
/**
 * Servicio de Archivos - Lógica de negocio
 * NO maneja HTTP ni sesiones, solo lógica pura
 * HomeLab AR - Roepard Labs
 */

require_once __DIR__ . '/../models/File.php';
require_once __DIR__ . '/StorageService.php';

class FileService
{
    private $fileModel;
    private $storageService;

    public function __construct()
    {
        $this->fileModel = new File();
        $this->storageService = new StorageService();
    }

    /**
     * Procesar subida de archivo
     * Combina validación, almacenamiento y registro en BD
     */
    public function uploadFile($file, $userId, $folderId = null, $description = '', $isShared = 0): array
    {
        try {
            // 1. Validar y guardar archivo físicamente
            $saveResult = $this->storageService->saveFile($file, $userId);

            if ($saveResult['status'] === 'error') {
                return $saveResult;
            }

            // 2. Preparar datos para BD
            $fileData = [
                'user_id' => $userId,
                'folder_id' => $folderId,
                'file_name' => $saveResult['data']['file_name'],
                'original_name' => $saveResult['data']['original_name'],
                'file_path' => $saveResult['data']['file_path'],
                'file_size' => $saveResult['data']['file_size'],
                'file_type' => $saveResult['data']['file_type'],
                'file_extension' => $saveResult['data']['file_extension'],
                'description' => $description,
                'is_shared' => $isShared
            ];

            // 3. Guardar en BD
            $fileId = $this->fileModel->create($fileData);

            if (!$fileId) {
                // Si falla BD, eliminar archivo físico
                $this->storageService->deleteFile($saveResult['data']['file_path']);
                return [
                    'status' => 'error',
                    'message' => 'Error al registrar archivo en base de datos'
                ];
            }

            return [
                'status' => 'success',
                'message' => 'Archivo subido exitosamente',
                'file_id' => $fileId,
                'file_data' => $fileData
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error al procesar archivo: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener archivos de un usuario
     * Admin puede ver todos, usuario solo los suyos
     */
    public function getUserFiles($userId, $folderId = null, $isAdmin = false): array
    {
        try {
            error_log("🔍 FileService::getUserFiles() - isAdmin: " . ($isAdmin ? 'true' : 'false') . ", folderId: " . ($folderId ?? 'null'));

            // CRÍTICO: Admin también debe filtrar por carpeta cuando navega dentro de una
            // Solo lista TODO cuando está en root
            if ($isAdmin && $folderId === null) {
                error_log("📂 Ruta: Admin en root (listAll + filter)");
                // Admin en root: ver todos los archivos del nivel root
                $files = $this->fileModel->listAll();
                // Filtrar solo archivos de nivel root (folder_id IS NULL)
                $files = array_filter($files, function ($file) {
                    return $file['folder_id'] === null;
                });
                error_log("📊 Archivos de root encontrados: " . count($files));
            } elseif ($isAdmin && $folderId !== null) {
                error_log("📂 Ruta: Admin en carpeta (listByFolder)");
                // Admin navegando dentro de carpeta: ver archivos de esa carpeta
                $files = $this->fileModel->listByFolder($folderId);
                error_log("📊 Archivos en carpeta $folderId: " . count($files));
            } else {
                error_log("📂 Ruta: Usuario normal (listByUserAndFolder)");
                // Usuario normal: ver solo sus archivos
                $files = $this->fileModel->listByUserAndFolder($userId, $folderId);
                error_log("📊 Archivos del usuario en carpeta: " . count($files));
            }

            // Enriquecer datos de archivos con información adicional
            foreach ($files as &$file) {
                $file['file_size_formatted'] = $this->storageService->formatFileSize($file['file_size']);
                $file['file_type_category'] = $this->storageService->getFileTypeCategory($file['file_extension']);
                $file['type'] = 'file'; // Marcar como archivo
            }

            // Obtener carpetas del mismo nivel
            require_once __DIR__ . '/../models/Folder.php';
            $folderModel = new Folder();

            // CRÍTICO: Filtrar carpetas por nivel (parent_folder_id)
            // Tanto admin como usuario deben ver solo carpetas del nivel actual
            $folders = [];
            if ($isAdmin) {
                // Admin ve todas las carpetas del nivel actual
                $allFolders = $folderModel->listAll();
                foreach ($allFolders as $folder) {
                    // Filtrar por parent_folder_id para mostrar solo carpetas del nivel actual
                    if ($folderId === null && $folder['parent_folder_id'] === null) {
                        $folders[] = $folder;
                    } elseif ($folderId !== null && $folder['parent_folder_id'] == $folderId) {
                        $folders[] = $folder;
                    }
                }
            } else {
                // Usuario solo ve sus carpetas del nivel actual
                $folders = $folderModel->listByUser($userId, $folderId);
            }

            // Enriquecer datos de carpetas
            foreach ($folders as &$folder) {
                $folder['type'] = 'folder'; // Marcar como carpeta
                $folder['file_size_formatted'] = '-'; // Carpetas no tienen tamaño
                $folder['file_type_category'] = 'folder';
                $folder['file_extension'] = null;
                // Renombrar campos para compatibilidad con frontend
                $folder['name'] = $folder['folder_name'];
                $folder['id'] = $folder['folder_id'];
                $folder['folderId'] = $folder['parent_folder_id'] ?? 'root';
                $folder['date'] = $folder['created_at'];
            }

            // Combinar archivos y carpetas
            $items = array_merge($folders, $files);

            return [
                'status' => 'success',
                'files' => $items,
                'total' => count($items)
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error al obtener archivos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener información de un archivo específico
     */
    public function getFileDetails($fileId, $userId, $isAdmin = false): array
    {
        try {
            $file = $this->fileModel->findById($fileId);

            if (!$file) {
                return [
                    'status' => 'error',
                    'message' => 'Archivo no encontrado'
                ];
            }

            // Verificar permisos: admin puede ver todo, usuario solo sus archivos
            if (!$isAdmin && $file['user_id'] != $userId) {
                return [
                    'status' => 'error',
                    'message' => 'No tienes permiso para acceder a este archivo'
                ];
            }

            // Verificar que el archivo físico existe
            $file['file_exists'] = $this->storageService->fileExists($file['file_path']);
            $file['file_size_formatted'] = $this->storageService->formatFileSize($file['file_size']);
            $file['file_type_category'] = $this->storageService->getFileTypeCategory($file['file_extension']);

            return [
                'status' => 'success',
                'file' => $file
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error al obtener archivo: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar metadata de archivo
     */
    public function updateFile($fileId, $userId, $data, $isAdmin = false): array
    {
        try {
            // Verificar que el archivo existe y permisos
            $fileCheck = $this->getFileDetails($fileId, $userId, $isAdmin);
            if ($fileCheck['status'] === 'error') {
                return $fileCheck;
            }

            // Actualizar solo metadata permitida
            $updateData = [
                'original_name' => $data['filename'] ?? $fileCheck['file']['original_name'], // Nombre visible para el usuario
                'description' => $data['description'] ?? $fileCheck['file']['description'],
                'is_shared' => $data['is_shared'] ?? $fileCheck['file']['is_shared']
            ];

            $success = $this->fileModel->update($fileId, $updateData);

            if (!$success) {
                return [
                    'status' => 'error',
                    'message' => 'Error al actualizar archivo'
                ];
            }

            return [
                'status' => 'success',
                'message' => 'Archivo actualizado exitosamente'
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error al actualizar archivo: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar archivo (BD y físico)
     */
    public function deleteFile($fileId, $userId, $isAdmin = false): array
    {
        try {
            // Verificar que el archivo existe y permisos
            $fileCheck = $this->getFileDetails($fileId, $userId, $isAdmin);
            if ($fileCheck['status'] === 'error') {
                return $fileCheck;
            }

            $file = $fileCheck['file'];

            // 1. Eliminar de BD primero
            $dbDeleted = $this->fileModel->delete($fileId);

            if (!$dbDeleted) {
                return [
                    'status' => 'error',
                    'message' => 'Error al eliminar archivo de base de datos'
                ];
            }

            // 2. Eliminar archivo físico
            $fileDeleted = $this->storageService->deleteFile($file['file_path']);

            if (!$fileDeleted) {
                return [
                    'status' => 'warning',
                    'message' => 'Archivo eliminado de BD pero no del servidor (ya no existía)'
                ];
            }

            return [
                'status' => 'success',
                'message' => 'Archivo eliminado exitosamente'
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error al eliminar archivo: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar carpeta
     */
    public function deleteFolder($folderId, $userId, $isAdmin = false): array
    {
        try {
            require_once __DIR__ . '/../models/Folder.php';
            $folderModel = new Folder();

            // Verificar que la carpeta existe
            $folder = $folderModel->findById($folderId);

            if (!$folder) {
                return [
                    'status' => 'error',
                    'message' => 'Carpeta no encontrada'
                ];
            }

            // Verificar permisos: admin puede eliminar todo, usuario solo sus carpetas
            if (!$isAdmin && $folder['user_id'] != $userId) {
                return [
                    'status' => 'error',
                    'message' => 'No tienes permiso para eliminar esta carpeta'
                ];
            }

            // TODO: En el futuro, eliminar recursivamente archivos y subcarpetas
            // Por ahora, solo eliminamos la carpeta (la BD debe tener CASCADE configurado)

            // Eliminar carpeta de BD
            $deleted = $folderModel->delete($folderId);

            if ($deleted) {
                return [
                    'status' => 'success',
                    'message' => 'Carpeta eliminada exitosamente'
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'No se pudo eliminar la carpeta'
                ];
            }

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error al eliminar carpeta: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Preparar archivo para descarga
     */
    public function prepareDownload($fileId, $userId, $isAdmin = false): array
    {
        try {
            // Verificar que el archivo existe y permisos
            $fileCheck = $this->getFileDetails($fileId, $userId, $isAdmin);
            if ($fileCheck['status'] === 'error') {
                return $fileCheck;
            }

            $file = $fileCheck['file'];

            // Verificar que el archivo físico existe
            if (!$file['file_exists']) {
                return [
                    'status' => 'error',
                    'message' => 'El archivo físico no existe en el servidor'
                ];
            }

            // Incrementar contador de descargas
            $this->fileModel->incrementDownloads($fileId);

            // Retornar información para descarga
            return [
                'status' => 'success',
                'file_path' => $this->storageService->getFullPath($file['file_path']),
                'original_name' => $file['original_name'],
                'file_type' => $file['file_type'],
                'file_size' => $file['file_size']
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error al preparar descarga: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener estadísticas de almacenamiento de usuario
     */
    public function getUserStats($userId): array
    {
        try {
            $stats = $this->fileModel->getUserStats($userId);

            return [
                'status' => 'success',
                'stats' => [
                    'total_files' => (int) $stats['total_files'],
                    'total_folders' => (int) $stats['total_folders'],
                    'total_size' => (int) $stats['total_size'],
                    'total_size_formatted' => $this->storageService->formatFileSize($stats['total_size']),
                    'shared_files' => (int) $stats['shared_files'],
                    'max_storage' => 10737418240, // 10 GB en bytes
                    'max_storage_formatted' => '10 GB',
                    'usage_percent' => round(($stats['total_size'] / 10737418240) * 100, 2)
                ]
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Buscar archivos
     */
    public function searchFiles($userId, $searchTerm, $isAdmin = false): array
    {
        try {
            $files = $this->fileModel->search($userId, $searchTerm, $isAdmin);

            // Enriquecer datos
            foreach ($files as &$file) {
                $file['file_size_formatted'] = $this->storageService->formatFileSize($file['file_size']);
                $file['file_type_category'] = $this->storageService->getFileTypeCategory($file['file_extension']);
            }

            return [
                'status' => 'success',
                'files' => $files,
                'total' => count($files)
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error al buscar archivos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Crear carpeta
     */
    public function createFolder($data): array
    {
        try {
            // Validar nombre de carpeta
            if (empty($data['name'])) {
                return [
                    'status' => 'error',
                    'message' => 'El nombre de la carpeta es obligatorio'
                ];
            }

            // Validar longitud del nombre
            if (strlen($data['name']) > 255) {
                return [
                    'status' => 'error',
                    'message' => 'El nombre de la carpeta es demasiado largo'
                ];
            }

            // Preparar datos para el modelo
            $folderData = [
                'user_id' => $data['user_id'],
                'parent_folder_id' => (!empty($data['parent_folder']) && $data['parent_folder'] !== 'root')
                    ? (int) $data['parent_folder']
                    : null,
                'folder_name' => $data['name'],
                'folder_path' => $this->generateFolderPath($data['name'], $data['user_id']),
                'description' => $data['description'] ?? '',
                'is_shared' => 0 // Por defecto no compartida
            ];

            // Usar el modelo Folder para crear
            require_once __DIR__ . '/../models/Folder.php';
            $folderModel = new Folder();
            $folderId = $folderModel->create($folderData);

            if ($folderId) {
                return [
                    'status' => 'success',
                    'message' => 'Carpeta creada exitosamente',
                    'folder_id' => $folderId,
                    'folder_name' => $data['name']
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'No se pudo crear la carpeta'
                ];
            }

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error al crear carpeta: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generar ruta de carpeta
     */
    private function generateFolderPath($folderName, $userId): string
    {
        // Crear slug del nombre de carpeta
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $folderName)));

        // Ruta: /user_X/carpeta-nombre
        return "/user_{$userId}/{$slug}";
    }
}
