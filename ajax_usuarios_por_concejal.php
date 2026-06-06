<?php
session_start();
require_once 'conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$rol = $_SESSION['rol'] ?? 'user';

$id_concejal = $_GET['id_concejal'] ?? null;
if (!$id_concejal || !is_numeric($id_concejal)) {
    echo json_encode([]);
    exit;
}

/* ===============================
   SEGURIDAD POR ROL
================================ */

// Si es concejal, solo puede pedir SUS usuarios
if ($rol === 'concejal') {

    $stmt = $conn->prepare("
        SELECT id
        FROM concejales
        WHERE nro_cedula = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $_SESSION['usuario']);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if (!$res || $res['id'] != $id_concejal) {
        http_response_code(403);
        echo json_encode([]);
        exit;
    }
}

// Admin puede pedir cualquier concejal
// User no debería usar este endpoint
if ($rol === 'user') {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

/* ===============================
   CONSULTA USUARIOS
================================ */

$stmt = $conn->prepare("
    SELECT id, nombre_completo AS nombre
    FROM usuarios
    WHERE id_concejal = ?
    ORDER BY nombre_completo
");
$stmt->bind_param("i", $id_concejal);
$stmt->execute();

$usuarios = [];
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $usuarios[] = $row;
}

echo json_encode($usuarios);