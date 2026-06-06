<?php
session_start();

if (!isset($_SESSION['usuario']) ||
    !in_array($_SESSION['rol'], ['admin', 'concejal'])
) {
    header('Location: index');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID inválido");
}

include 'conexion.php';

$id = intval($_GET['id']);

// Preparar sentencia para evitar inyección SQL
$stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    // Redirigir de vuelta con mensaje de éxito
    header("Location: usuarios_lista?msg=Usuario+eliminado+correctamente");
} else {
    echo "Error al eliminar usuario.";
}

$stmt->close();
$conn->close();
