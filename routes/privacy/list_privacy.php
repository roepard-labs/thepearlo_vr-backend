<?php
/**
 * Ruta API Admin: Listar Política de Privacidad
 * GET /routes/privacy/list_privacy.php
 * 
 * Retorna todo el contenido (incluye inactivos) para administración
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

    // Solo GET permitido
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'status' => 'error',
            'message' => 'Método no permitido. Use GET.'
        ]);
        exit;
    }

    $model = new LegalPrivacy();

    // Obtener todo el contenido (incluye inactivos)
    $content = $model->getAll();

    // Obtener metadata
    $metadata = $model->getMetadata();

    // Agrupar por secciones
    $sections = [];
    foreach ($content as $item) {
        $sectionNum = $item['section_number'];

        if (!isset($sections[$sectionNum])) {
            $sections[$sectionNum] = [
                'section_number' => $sectionNum,
                'section_title' => $item['section_title'],
                'paragraphs' => []
            ];
        }

        $sections[$sectionNum]['paragraphs'][] = $item;
    }

    // Reindexar array
    $sections = array_values($sections);

    echo json_encode([
        'status' => 'success',
        'message' => 'Contenido de privacidad obtenido',
        'data' => [
            'metadata' => $metadata,
            'sections' => $sections,
            'total_sections' => count($sections),
            'total_paragraphs' => count($content)
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al obtener contenido: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
