<?php

$path = trim((string) ($_GET['path'] ?? ''), '/');

if ($path === '') {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path = trim($uri, '/');
}

if ($path === '' || $path === 'api/index.php') {
    $path = 'index';
}

if (str_ends_with($path, '.php')) {
    $path = substr($path, 0, -4);
}

if (!preg_match('/^[a-zA-Z0-9_-]+$/', $path)) {
    http_response_code(404);
    exit('Pagina no encontrada.');
}

$archivo = dirname(__DIR__) . '/' . $path . '.php';
if (!is_file($archivo)) {
    http_response_code(404);
    exit('Pagina no encontrada.');
}

chdir(dirname(__DIR__));
require $archivo;
