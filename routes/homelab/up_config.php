<?php
/**
 * RUTA: Actualizar configuración Homelab del usuario autenticado
 * MÉTODO: POST
 * ENDPOINT: /routes/homelab/up_config.php
 * RESPUESTA: JSON
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../middleware/user.php';
require_once __DIR__ . '/../../middleware/status.php';
require_once __DIR__ . '/../../services/HomelabConfigService.php';

header('Content-Type: application/json');

// Aceptar POST (por compatibilidad con frontend existente)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['status' => 'error', 'message' => 'Método no permitido. Use POST.']);
	exit;
}

// Leer cuerpo JSON
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if ($input === null && !empty($raw)) {
	http_response_code(400);
	echo json_encode(['status' => 'error', 'message' => 'JSON inválido en el cuerpo de la petición']);
	exit;
}

try {
	$userId = Auth::requireAuth();
	Status::checkStatus(1);

	$service = new HomelabConfigService();
	$result = $service->upsertConfig((int)$userId, is_array($input) ? $input : []);

	if (isset($result['status']) && $result['status'] === 'success') {
		http_response_code(200);
		echo json_encode($result);
		exit;
	}

	// Error de validación o guardado
	http_response_code(400);
	echo json_encode($result);

} catch (Exception $e) {
	http_response_code(500);
	echo json_encode(['status' => 'error', 'message' => 'Error interno', 'details' => $e->getMessage()]);
}


