<?php

// Cargar la clase EnvLoader
require_once __DIR__ . '/env.php';

// Cargar el archivo .env
EnvLoader::load(__DIR__ . '/../.env');

// Retornar la configuración usando variables de entorno
return [
    'driver' => EnvLoader::get('DB_CONNECTION'),
    'host' => EnvLoader::get('DB_HOST'),
    'dbname' => EnvLoader::get('DB_DATABASE'),
    'port' => EnvLoader::get('DB_PORT'),
    'user' => EnvLoader::get('DB_USERNAME'),
    'password' => EnvLoader::get('DB_PASSWORD'),
    'charset' => EnvLoader::get('DB_CHARSET')
];