<?php
/**
 * Backend API - Entry Point
 * Redirige al website cuando se accede a la ruta raíz (/)
 */

// Cargar variables de entorno
require_once __DIR__ . '/config/env.php';

// Cargar y aplicar CORS
require_once __DIR__ . '/config/cors.php';

// Obtener la URL del website desde las variables de entorno
$websiteUrl = EnvLoader::get('WEBSITE_URL', 'https://thepearlo.com');

// Redirigir a la URL del website
header("Location: $websiteUrl");
exit;

