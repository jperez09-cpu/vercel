<?php
session_start();
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['admin', 'concejal'])) {
    header('Location: login');
    exit;
}

include 'conexion.php';

$id = intval($_POST['id']);
$nombre_completo = $_POST['nombre_completo'];
$nombre_usuario = $_POST['nombre_usuario'];
$rol = $_POST['rol']?? 'user';
$id_barrio = $_POST['id_barrio'];
$zona = $_POST['zona'] ?? '';
$telefono = $_POST['telefono'];

$id_concejal = $_POST['id_concejal'] ?? null;
if ($id_concejal === '') {
    $id_concejal = null;
}

$stmt = $conn->prepare("UPDATE usuarios SET nombre_completo=?, nombre_usuario=?, rol=?, id_barrio=?, zona=?, telefono=?, id_concejal=? WHERE id=?");
$stmt->bind_param("sssissii", $nombre_completo, $nombre_usuario, $rol, $id_barrio, $zona, $telefono,$id_concejal, $id);
//$stmt->execute();

if (!$stmt->execute()) {
    die("Error al ejecutar: " . $stmt->error);
}

header("Location: usuarios_lista");
exit;
