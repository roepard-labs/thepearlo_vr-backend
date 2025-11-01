<?php
/**
 * RUTA: Status de la API
 * Endpoint público para verificar que la API está funcionando
 */

// Aplicar CORS headers
require_once __DIR__ . '/../../config/cors.php';

header('Content-Type: application/json');

$return = [
    'status' => 'success',
    'message' => 'API is running',
    'timestamp' => date('Y-m-d H:i:s')
];

echo json_encode($return);  