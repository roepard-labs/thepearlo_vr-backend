<?php
/**
 * RUTA: Obtener configuración Homelab del usuario autenticado
 * MÉTODO: GET
 * ENDPOINT: /routes/homelab/list_config.php
 * RESPUESTA: JSON
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../middleware/user.php';
require_once __DIR__ . '/../../middleware/status.php';
require_once __DIR__ . '/../../services/HomelabConfigService.php';

header('Content-Type: application/json');

// Solo GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
	http_response_code(405);
	echo json_encode(['status' => 'error', 'message' => 'Método no permitido. Use GET.']);
	exit;
}

try {
	// Verificar autenticación y estado del usuario
	$userId = Auth::requireAuth();
	Status::checkStatus(1);

	$service = new HomelabConfigService();
	$config = $service->getConfig((int)$userId);

	http_response_code(200);
	echo json_encode([
		'status' => 'success',
		'message' => 'Configuración obtenida',
		'data' => $config
	]);
} catch (Exception $e) {
	http_response_code(500);
	echo json_encode(['status' => 'error', 'message' => 'Error interno', 'details' => $e->getMessage()]);
}


