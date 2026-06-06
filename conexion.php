<?php

$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$db = getenv('DB_NAME');
$port = getenv('DB_PORT') ?: '10498';

if (!$host || !$user || !$pass || !$db) {
    die('Faltan variables de entorno para conectar a la base de datos.');
}

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die('Conexion fallida: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
