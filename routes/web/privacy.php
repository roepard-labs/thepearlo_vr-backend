<?php
/**
 * Ruta API Pública: Obtener Política de Privacidad
 * GET /routes/web/privacy.php
 * 
 * Retorna el contenido activo de la política de privacidad
 * HomeLab AR - Roepard Labs
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../models/LegalPrivacy.php';

try {
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

    // Obtener contenido activo
    $content = $model->getAllActive();

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

        $sections[$sectionNum]['paragraphs'][] = [
            'paragraph_number' => $item['paragraph_number'],
            'content' => $item['paragraph_content']
        ];
    }

    // Reindexar array
    $sections = array_values($sections);

    echo json_encode([
        'status' => 'success',
        'message' => 'Política de privacidad obtenida',
        'data' => [
            'metadata' => [
                'version' => $metadata['version'] ?? '1.0',
                'effective_date' => $metadata['effective_date'] ?? date('Y-m-d'),
                'last_updated' => $metadata['last_updated'] ?? date('Y-m-d H:i:s')
            ],
            'sections' => $sections,
            'total_sections' => count($sections)
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al obtener política de privacidad: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
