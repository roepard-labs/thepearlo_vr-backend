<?php
/**
 * Ruta: Eliminar Archivo
 * DELETE /routes/files/delete_file.php?file_id=X
 * HomeLab AR - Roepard Labs
 */

// Headers CORS
require_once __DIR__ . '/../../config/cors.php';

// Iniciar sesión
require_once __DIR__ . '/../../core/session.php';

// Controlador
require_once __DIR__ . '/../../controllers/FileController.php';

// Instanciar controlador
$controller = new FileController();

// Ejecutar acción
$controller->delete();
