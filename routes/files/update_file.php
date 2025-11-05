<?php
/**
 * Ruta: Actualizar Archivo
 * PUT /routes/files/update_file.php
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
$controller->update();
