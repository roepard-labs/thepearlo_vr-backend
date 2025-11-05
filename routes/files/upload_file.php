<?php
/**
 * Ruta: Subir Archivo
 * POST /routes/files/upload_file.php
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
$controller->upload();
