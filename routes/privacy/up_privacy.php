<?php
/**
 * Ruta API Admin: Actualizar Política de Privacidad
 * PUT /routes/privacy/up_privacy.php
 * 
 * Permite crear, actualizar o eliminar párrafos de privacidad
 * Requiere autenticación y rol de administrador
 * HomeLab AR - Roepard Labs
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../middleware/user.php';
require_once __DIR__ . '/../../middleware/status.php';
require_once __DIR__ . '/../../models/LegalPrivacy.php';

try {
    // Verificar autenticación
    Auth::checkAuth();

    // Verificar que el usuario esté activo
    Status::checkStatus(1);

    // Verificar que sea administrador (role_id = 2)
    if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 2) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Acceso denegado. Se requiere rol de administrador.'
        ]);
        exit;
    }

    // Solo POST/PUT permitido
    if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
        http_response_code(405);
        echo json_encode([
            'status' => 'error',
            'message' => 'Método no permitido. Use POST o PUT.'
        ]);
        exit;
    }

    // Obtener datos del request
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Datos inválidos o vacíos'
        ]);
        exit;
    }

    $model = new LegalPrivacy();
    $userId = $_SESSION['user_id'];

    // Operación: create, update, delete
    $operation = $input['operation'] ?? 'update';

    switch ($operation) {
        case 'create':
            // Validar campos requeridos
            $required = ['section_number', 'section_title', 'paragraph_number', 'paragraph_content'];
            foreach ($required as $field) {
                if (!isset($input[$field]) || empty($input[$field])) {
                    http_response_code(400);
                    echo json_encode([
                        'status' => 'error',
                        'message' => "Campo requerido faltante: {$field}"
                    ]);
                    exit;
                }
            }

            $data = [
                'section_number' => (int) $input['section_number'],
                'section_title' => trim($input['section_title']),
                'paragraph_number' => (int) $input['paragraph_number'],
                'paragraph_content' => trim($input['paragraph_content']),
                'is_active' => $input['is_active'] ?? 1,
                'display_order' => $input['display_order'] ?? 0,
                'created_by' => $userId
            ];

            $newId = $model->create($data);

            echo json_encode([
                'status' => 'success',
                'message' => 'Párrafo creado exitosamente',
                'data' => ['privacy_id' => $newId]
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'update':
            // Validar ID
            if (!isset($input['privacy_id']) || empty($input['privacy_id'])) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'ID de párrafo requerido'
                ]);
                exit;
            }

            $privacyId = (int) $input['privacy_id'];

            // Verificar que existe
            $existing = $model->getById($privacyId);
            if (!$existing) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Párrafo no encontrado'
                ]);
                exit;
            }

            $data = [
                'section_number' => $input['section_number'] ?? $existing['section_number'],
                'section_title' => $input['section_title'] ?? $existing['section_title'],
                'paragraph_number' => $input['paragraph_number'] ?? $existing['paragraph_number'],
                'paragraph_content' => $input['paragraph_content'] ?? $existing['paragraph_content'],
                'is_active' => $input['is_active'] ?? $existing['is_active'],
                'display_order' => $input['display_order'] ?? $existing['display_order'],
                'updated_by' => $userId
            ];

            $model->update($privacyId, $data);

            echo json_encode([
                'status' => 'success',
                'message' => 'Párrafo actualizado exitosamente'
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'delete':
            // Validar ID
            if (!isset($input['privacy_id']) || empty($input['privacy_id'])) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'ID de párrafo requerido'
                ]);
                exit;
            }

            $privacyId = (int) $input['privacy_id'];
            $model->delete($privacyId, $userId);

            echo json_encode([
                'status' => 'success',
                'message' => 'Párrafo eliminado exitosamente'
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'update_metadata':
            // Actualizar metadata del documento
            $metaData = [
                'version' => $input['version'] ?? '1.0',
                'effective_date' => $input['effective_date'] ?? date('Y-m-d'),
                'updated_by' => $userId,
                'change_log' => $input['change_log'] ?? ''
            ];

            $model->updateMetadata($metaData);

            echo json_encode([
                'status' => 'success',
                'message' => 'Metadata actualizada exitosamente'
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Operación no válida. Use: create, update, delete, update_metadata'
            ]);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al procesar solicitud: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
